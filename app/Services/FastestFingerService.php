<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;

/**
 * Fastest Finger First.
 *
 * Contenders are shown four items and must put them in the right order.
 * Whoever is correct in the shortest time takes the hot seat. Timing is
 * measured against the server's start moment, not the phone's clock, so a
 * slow handset cannot be gamed and a fast one gains nothing.
 */
final class FastestFingerService
{
    public function __construct(private Database $db)
    {
    }

    public static function make(?Database $db = null): self
    {
        return new self($db ?? Database::instance());
    }

    /**
     * Start a round.
     *
     * @param array<int,int> $participantIds
     * @return array<string,mixed>
     */
    public function create(string $questionText, array $order, array $participantIds, ?int $operatorId, ?int $timeLimit = null): array
    {
        $questionText = trim($questionText);
        if ($questionText === '') {
            throw new HttpException(422, 'The question is required.');
        }

        $order = strtoupper(implode('', array_map('trim', $order)));
        if (!$this->isValidOrder($order)) {
            throw new HttpException(422, 'The correct order must use A, B, C and D exactly once each.');
        }

        $participantIds = array_values(array_unique(array_filter(array_map('intval', $participantIds))));
        if (count($participantIds) < 2) {
            throw new HttpException(422, 'Choose at least two contenders.');
        }

        // Only one round may be open at a time.
        $running = $this->currentRound();
        if ($running !== null && (string) $running['status'] !== 'closed') {
            throw new HttpException(409, 'A Fastest Finger round is already open. Close it first.');
        }

        $limit = $timeLimit ?? SettingsService::int('fff_time_limit', 25);
        $now = date('Y-m-d H:i:s');

        return $this->db->transaction(function () use ($questionText, $order, $participantIds, $operatorId, $limit, $now): array {
            $roundId = $this->db->insert('fff_rounds', [
                'question_text' => $questionText,
                'correct_order' => $order,
                'time_limit'    => max(5, min(300, $limit)),
                'status'        => 'pending',
                'state_version' => 1,
                'operator_id'   => $operatorId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            foreach ($participantIds as $participantId) {
                $exists = (int) ($this->db->scalar('SELECT COUNT(*) FROM participants WHERE id = ?', [$participantId]) ?? 0);
                if ($exists === 0) {
                    continue;
                }
                $this->db->insert('fff_entries', [
                    'round_id'       => $roundId,
                    'participant_id' => $participantId,
                    'access_code'    => $this->uniqueAccessCode($roundId),
                    'created_at'     => $now,
                ]);
            }

            AuditService::log('fff.created', 'Created a Fastest Finger round with ' . count($participantIds) . ' contenders.', 'fff', $roundId);
            return $this->state($roundId);
        });
    }

    /** Reveals the question and starts the clock. */
    public function start(int $roundId): array
    {
        $round = $this->mustFind($roundId);
        if ((string) $round['status'] === 'closed') {
            throw new HttpException(409, 'That round has already finished.');
        }

        // Backfill codes for rounds created before access codes existed.
        foreach ($this->db->select(
            "SELECT id FROM fff_entries WHERE round_id = ? AND (access_code IS NULL OR access_code = '')",
            [$roundId]
        ) as $row) {
            $this->db->update('fff_entries', ['access_code' => $this->uniqueAccessCode($roundId)], ['id' => (int) $row['id']]);
        }

        $this->db->update('fff_rounds', [
            'status'        => 'running',
            'started_at'    => round(microtime(true), 3),
            'state_version' => (int) $round['state_version'] + 1,
            'updated_at'    => date('Y-m-d H:i:s'),
        ], ['id' => $roundId]);

        AuditService::log('fff.started', 'Started Fastest Finger round #' . $roundId, 'fff', $roundId);
        return $this->state($roundId);
    }

    /**
     * Record one contender's answer. Time is measured from the server's
     * start moment.
     *
     * @param array<int,string> $order
     */
    public function submit(int $roundId, int $participantId, array $order): array
    {
        $round = $this->mustFind($roundId);
        if ((string) $round['status'] !== 'running') {
            throw new HttpException(409, 'This round is not accepting answers.');
        }

        $submitted = strtoupper(implode('', array_map('trim', $order)));
        if (!$this->isValidOrder($submitted)) {
            throw new HttpException(422, 'The order must use A, B, C and D exactly once each.');
        }

        $entry = $this->db->selectOne(
            'SELECT * FROM fff_entries WHERE round_id = ? AND participant_id = ? LIMIT 1',
            [$roundId, $participantId]
        );
        if ($entry === null) {
            throw new HttpException(404, 'That contender is not in this round.');
        }
        if ($entry['submitted_at'] !== null) {
            throw new HttpException(409, 'That contender has already answered.');
        }

        $startedAt = (float) $round['started_at'];
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        $limitMs = (int) $round['time_limit'] * 1000;

        if ($elapsedMs > $limitMs) {
            $elapsedMs = $limitMs;
        }

        $isCorrect = $submitted === (string) $round['correct_order'];

        $this->db->update('fff_entries', [
            'submitted_order' => $submitted,
            'is_correct'      => $isCorrect ? 1 : 0,
            'time_taken_ms'   => $elapsedMs,
            'submitted_at'    => date('Y-m-d H:i:s'),
        ], ['id' => (int) $entry['id']]);

        $this->bump($roundId);
        return $this->state($roundId);
    }

    /**
     * Submit from a contender's own phone, identified by their access code.
     *
     * @param array<int,string> $order
     */
    public function submitByCode(int $roundId, string $accessCode, array $order): array
    {
        $entry = $this->db->selectOne(
            'SELECT participant_id FROM fff_entries WHERE round_id = ? AND access_code = ? LIMIT 1',
            [$roundId, strtoupper(trim($accessCode))]
        );
        if ($entry === null) {
            throw new HttpException(404, 'That code is not valid for this round.');
        }
        return $this->submit($roundId, (int) $entry['participant_id'], $order);
    }

    /** @return array<string,mixed>|null The contender behind an access code. */
    public function contenderByCode(int $roundId, string $accessCode): ?array
    {
        return $this->db->selectOne(
            'SELECT e.participant_id, e.submitted_at, p.name
             FROM fff_entries e INNER JOIN participants p ON p.id = e.participant_id
             WHERE e.round_id = ? AND e.access_code = ? LIMIT 1',
            [$roundId, strtoupper(trim($accessCode))]
        );
    }

    private function uniqueAccessCode(int $roundId): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exists = (int) ($this->db->scalar(
                'SELECT COUNT(*) FROM fff_entries WHERE round_id = ? AND access_code = ?',
                [$roundId, $code]
            ) ?? 0);
        } while ($exists > 0);
        return $code;
    }

    /** Closes the round and ranks everyone. */
    public function close(int $roundId): array
    {
        $round = $this->mustFind($roundId);

        return $this->db->transaction(function () use ($roundId, $round): array {
            // Correct answers rank by time; wrong or missing answers never rank.
            $entries = $this->db->select(
                'SELECT id FROM fff_entries
                 WHERE round_id = ? AND is_correct = 1 AND time_taken_ms IS NOT NULL
                 ORDER BY time_taken_ms ASC, id ASC',
                [$roundId]
            );

            $winnerId = null;
            foreach ($entries as $position => $entry) {
                $this->db->update('fff_entries', ['rank_position' => $position + 1], ['id' => (int) $entry['id']]);
                if ($position === 0) {
                    $winnerId = (int) ($this->db->scalar(
                        'SELECT participant_id FROM fff_entries WHERE id = ?',
                        [(int) $entry['id']]
                    ) ?? 0);
                }
            }
            // Anyone who was wrong or silent loses any stale rank.
            $this->db->run(
                'UPDATE fff_entries SET rank_position = NULL
                 WHERE round_id = ? AND (is_correct = 0 OR time_taken_ms IS NULL)',
                [$roundId]
            );

            $this->db->update('fff_rounds', [
                'status'                => 'closed',
                'closed_at'             => date('Y-m-d H:i:s'),
                'winner_participant_id' => $winnerId ?: null,
                'state_version'         => (int) $round['state_version'] + 1,
                'updated_at'            => date('Y-m-d H:i:s'),
            ], ['id' => $roundId]);

            AuditService::log(
                'fff.closed',
                $winnerId ? 'Fastest Finger won by participant #' . $winnerId : 'Fastest Finger closed with no correct answer.',
                'fff',
                $roundId
            );

            return $this->state($roundId);
        });
    }

    public function cancel(int $roundId): void
    {
        $this->db->run("UPDATE fff_rounds SET status = 'closed', closed_at = ?, updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $roundId]);
        AuditService::log('fff.cancelled', 'Cancelled Fastest Finger round #' . $roundId, 'fff', $roundId);
    }

    /** The round currently on air, if any. */
    public function currentRound(): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM fff_rounds WHERE status IN ('pending','running') ORDER BY id DESC LIMIT 1"
        );
    }

    /** @return array<string,mixed>|null */
    public function currentState(): ?array
    {
        $round = $this->currentRound();
        if ($round === null) {
            // Show the most recent closed round briefly so the result can be read.
            $round = $this->db->selectOne(
                "SELECT * FROM fff_rounds WHERE status = 'closed' AND closed_at > ? ORDER BY id DESC LIMIT 1",
                [date('Y-m-d H:i:s', time() - 120)]
            );
        }
        return $round === null ? null : $this->state((int) $round['id']);
    }

    /**
     * @param bool $includeAnswer Only the operator ever gets the right order.
     * @return array<string,mixed>
     */
    public function state(int $roundId, bool $includeAnswer = false): array
    {
        $round = $this->mustFind($roundId);
        $limitMs = (int) $round['time_limit'] * 1000;

        $remaining = $limitMs;
        if ((string) $round['status'] === 'running' && $round['started_at'] !== null) {
            $elapsed = (int) round((microtime(true) - (float) $round['started_at']) * 1000);
            $remaining = max(0, $limitMs - $elapsed);

            // Time is up: close it so both screens agree without anyone clicking.
            if ($remaining === 0) {
                $this->close($roundId);
                $round = $this->mustFind($roundId);
            }
        } elseif ((string) $round['status'] === 'closed') {
            $remaining = 0;
        }

        $entries = $this->db->select(
            'SELECT e.*, p.name, p.city, p.photo_path, p.registration_no
             FROM fff_entries e
             INNER JOIN participants p ON p.id = e.participant_id
             WHERE e.round_id = ?
             ORDER BY (e.rank_position IS NULL), e.rank_position ASC, e.time_taken_ms ASC, p.name ASC',
            [$roundId]
        );

        $contenders = [];
        $winner = null;
        foreach ($entries as $entry) {
            $answered = $entry['submitted_at'] !== null;
            $revealed = (string) $round['status'] === 'closed';

            $row = [
                'participant_id' => (int) $entry['participant_id'],
                'name'           => (string) $entry['name'],
                'city'           => (string) ($entry['city'] ?? ''),
                'reg_no'         => (string) ($entry['registration_no'] ?? ''),
                'photo'          => \App\Core\Application::uploadUrl($entry['photo_path'] ?? null),
                'answered'       => $answered,
                'time_ms'        => $entry['time_taken_ms'] === null ? null : (int) $entry['time_taken_ms'],
                'time_label'     => $entry['time_taken_ms'] === null ? '—' : number_format((int) $entry['time_taken_ms'] / 1000, 2) . 's',
                // Whether an answer was right is only revealed once the round closes.
                'is_correct'     => $revealed ? ((int) $entry['is_correct'] === 1) : null,
                'rank'           => $entry['rank_position'] === null ? null : (int) $entry['rank_position'],
                'order'          => $revealed ? ($entry['submitted_order'] ?? null) : null,
            ];
            $contenders[] = $row;

            if ($revealed && (int) $entry['participant_id'] === (int) ($round['winner_participant_id'] ?? 0)) {
                $winner = $row;
            }
        }

        $state = [
            'id'            => (int) $round['id'],
            'status'        => (string) $round['status'],
            'state_version' => (int) $round['state_version'],
            'question'      => (string) $round['question_text'],
            'time_limit'    => (int) $round['time_limit'],
            'remaining_ms'  => $remaining,
            'total_ms'      => $limitMs,
            'answered'      => count(array_filter($contenders, static fn ($c) => $c['answered'])),
            'contenders'    => $contenders,
            'winner'        => $winner,
            // The order is the answer - it only leaves the server once closed.
            'correct_order' => (string) $round['status'] === 'closed' ? (string) $round['correct_order'] : null,
        ];

        if ($includeAnswer) {
            $codes = [];
            foreach ($this->db->select(
                'SELECT participant_id, access_code FROM fff_entries WHERE round_id = ?',
                [$roundId]
            ) as $row) {
                $codes[(int) $row['participant_id']] = (string) ($row['access_code'] ?? '');
            }
            $state['private'] = [
                'correct_order' => (string) $round['correct_order'],
                'access_codes'  => $codes,
                'answer_url'    => \App\Core\Application::url('/fff/' . $roundId),
            ];
        }

        return $state;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $limit = 25): array
    {
        return $this->db->select(
            'SELECT r.*, p.name AS winner_name,
                    (SELECT COUNT(*) FROM fff_entries e WHERE e.round_id = r.id) AS contenders
             FROM fff_rounds r
             LEFT JOIN participants p ON p.id = r.winner_participant_id
             ORDER BY r.id DESC LIMIT ' . max(1, $limit)
        );
    }

    private function isValidOrder(string $order): bool
    {
        if (strlen($order) !== 4) {
            return false;
        }
        $letters = str_split($order);
        sort($letters);
        return $letters === ['A', 'B', 'C', 'D'];
    }

    private function bump(int $roundId): void
    {
        $this->db->run(
            'UPDATE fff_rounds SET state_version = state_version + 1, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $roundId]
        );
    }

    /** @return array<string,mixed> */
    private function mustFind(int $roundId): array
    {
        $round = $this->db->selectOne('SELECT * FROM fff_rounds WHERE id = ? LIMIT 1', [$roundId]);
        if ($round === null) {
            throw new HttpException(404, 'That Fastest Finger round could not be found.');
        }
        return $round;
    }
}
