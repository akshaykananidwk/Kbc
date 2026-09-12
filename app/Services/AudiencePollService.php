<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Support\Str;

/**
 * Live audience voting from phones.
 *
 * The operator opens a poll for the question on air; the display shows a QR
 * code; each phone votes once (and may change its mind until the poll
 * closes). If nobody votes, the lifeline falls back to the simulated poll
 * so the show never stalls waiting for a crowd.
 */
final class AudiencePollService
{
    public function __construct(private Database $db)
    {
    }

    public static function make(?Database $db = null): self
    {
        return new self($db ?? Database::instance());
    }

    /**
     * Open voting for the question currently on air.
     *
     * @return array<string,mixed>
     */
    public function open(int $gameId, int $questionId, int $levelNo): array
    {
        $this->closeExpired();

        $existing = $this->db->selectOne(
            "SELECT * FROM audience_polls
             WHERE game_id = ? AND question_id = ? AND status = 'open' LIMIT 1",
            [$gameId, $questionId]
        );
        if ($existing !== null) {
            return $this->describe($existing);
        }

        $window = max(5, SettingsService::int('audience_poll_window', 30));
        $code = $this->uniqueCode();

        $id = $this->db->insert('audience_polls', [
            'game_id'     => $gameId,
            'question_id' => $questionId,
            'level_no'    => $levelNo,
            'code'        => $code,
            'status'      => 'open',
            'opened_at'   => date('Y-m-d H:i:s'),
            'closes_at'   => date('Y-m-d H:i:s', time() + $window),
            'total_votes' => 0,
        ]);

        AuditService::log('poll.opened', 'Opened audience voting (' . $code . ') for level ' . $levelNo, 'poll', $id);

        $poll = $this->db->selectOne('SELECT * FROM audience_polls WHERE id = ?', [$id]);
        return $this->describe($poll ?? []);
    }

    /** @return array<string,mixed> */
    public function close(int $pollId): array
    {
        $poll = $this->db->selectOne('SELECT * FROM audience_polls WHERE id = ? LIMIT 1', [$pollId]);
        if ($poll === null) {
            throw new HttpException(404, 'That poll no longer exists.');
        }
        if ((string) $poll['status'] === 'open') {
            $this->db->update('audience_polls', [
                'status'      => 'closed',
                'closed_at'   => date('Y-m-d H:i:s'),
                'total_votes' => $this->voteCount($pollId),
            ], ['id' => $pollId]);
            AuditService::log('poll.closed', 'Closed audience voting ' . $poll['code'], 'poll', $pollId);
        }
        $poll = $this->db->selectOne('SELECT * FROM audience_polls WHERE id = ?', [$pollId]);
        return $this->describe($poll ?? []);
    }

    /** The poll a voter reached through the QR code. */
    public function findByCode(string $code): ?array
    {
        $poll = $this->db->selectOne('SELECT * FROM audience_polls WHERE code = ? LIMIT 1', [strtoupper(trim($code))]);
        return $poll === null ? null : $this->describe($poll);
    }

    /** The open poll for a game, if there is one. */
    public function openForGame(int $gameId): ?array
    {
        $this->closeExpired();
        $poll = $this->db->selectOne(
            "SELECT * FROM audience_polls WHERE game_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1",
            [$gameId]
        );
        return $poll === null ? null : $this->describe($poll);
    }

    /**
     * Record one phone's vote. A device may change its answer while the
     * poll is open, but cannot vote twice.
     *
     * @return array<string,mixed>
     */
    public function vote(string $code, string $option, string $voterToken, string $ip): array
    {
        $option = strtoupper(trim($option));
        if (!in_array($option, ['A', 'B', 'C', 'D'], true)) {
            throw new HttpException(422, 'Choose option A, B, C or D.');
        }
        if (strlen($voterToken) !== 32 || !ctype_xdigit($voterToken)) {
            throw new HttpException(422, 'Your voting session is not valid. Reload the page.');
        }

        $this->closeExpired();
        $poll = $this->db->selectOne('SELECT * FROM audience_polls WHERE code = ? LIMIT 1', [strtoupper(trim($code))]);
        if ($poll === null) {
            throw new HttpException(404, 'That vote is not open.');
        }
        if ((string) $poll['status'] !== 'open' || strtotime((string) $poll['closes_at']) < time()) {
            throw new HttpException(409, 'Voting has closed.');
        }

        $pollId = (int) $poll['id'];
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->selectOne(
            'SELECT id FROM audience_votes WHERE poll_id = ? AND voter_token = ? LIMIT 1',
            [$pollId, $voterToken]
        );

        if ($existing === null) {
            $this->db->insert('audience_votes', [
                'poll_id'     => $pollId,
                'voter_token' => $voterToken,
                'option_key'  => $option,
                'ip_hash'     => hash('sha256', $ip . '|' . $poll['code']),
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } else {
            $this->db->update('audience_votes', ['option_key' => $option, 'updated_at' => $now], ['id' => (int) $existing['id']]);
        }

        $this->db->update('audience_polls', ['total_votes' => $this->voteCount($pollId)], ['id' => $pollId]);

        return [
            'recorded' => true,
            'option'   => $option,
            'changed'  => $existing !== null,
            'closes_in'=> max(0, strtotime((string) $poll['closes_at']) - time()),
        ];
    }

    /**
     * Percentages for a poll. Returns null when nobody voted, so the caller
     * can fall back to the simulated poll.
     *
     * @param array<int,string> $removedOptions
     * @return array<string,mixed>|null
     */
    public function results(int $pollId, array $removedOptions = []): ?array
    {
        $rows = $this->db->select(
            'SELECT option_key, COUNT(*) AS votes FROM audience_votes WHERE poll_id = ? GROUP BY option_key',
            [$pollId]
        );

        $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        $total = 0;
        foreach ($rows as $row) {
            $key = (string) $row['option_key'];
            if (in_array($key, $removedOptions, true)) {
                continue; // an option 50:50 removed cannot receive a share
            }
            $counts[$key] = (int) $row['votes'];
            $total += (int) $row['votes'];
        }

        if ($total === 0) {
            return null;
        }

        $percentages = [];
        foreach ($counts as $key => $votes) {
            $percentages[$key] = (int) round($votes / $total * 100);
        }

        // Rounding can leave the total at 99 or 101.
        $difference = 100 - array_sum($percentages);
        if ($difference !== 0) {
            $highest = array_search(max($percentages), $percentages, true);
            if (is_string($highest)) {
                $percentages[$highest] += $difference;
            }
        }

        return [
            'percentages' => $percentages,
            'counts'      => $counts,
            'total_votes' => $total,
            'mode'        => 'live',
        ];
    }

    public function voteCount(int $pollId): int
    {
        return (int) ($this->db->scalar('SELECT COUNT(*) FROM audience_votes WHERE poll_id = ?', [$pollId]) ?? 0);
    }

    /** Closes any poll whose window has elapsed. */
    public function closeExpired(): void
    {
        $this->db->run(
            "UPDATE audience_polls SET status = 'closed', closed_at = ?
             WHERE status = 'open' AND closes_at < ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
    }

    /** @return array<string,mixed> */
    private function describe(array $poll): array
    {
        if ($poll === []) {
            return [];
        }
        $pollId = (int) $poll['id'];
        $closesIn = max(0, strtotime((string) $poll['closes_at']) - time());
        $isOpen = (string) $poll['status'] === 'open' && $closesIn > 0;

        return [
            'id'          => $pollId,
            'code'        => (string) $poll['code'],
            'status'      => $isOpen ? 'open' : 'closed',
            'game_id'     => (int) $poll['game_id'],
            'question_id' => (int) $poll['question_id'],
            'level_no'    => (int) $poll['level_no'],
            'closes_in'   => $closesIn,
            'total_votes' => $this->voteCount($pollId),
            'vote_url'    => \App\Core\Application::url('/vote/' . $poll['code']),
        ];
    }

    private function uniqueCode(): string
    {
        // Unambiguous characters only - this gets read off a TV screen.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exists = (int) ($this->db->scalar('SELECT COUNT(*) FROM audience_polls WHERE code = ?', [$code]) ?? 0);
        } while ($exists > 0);

        return $code;
    }
}
