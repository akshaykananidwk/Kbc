<?php
declare(strict_types=1);

namespace App\Repositories;

final class GameRepository extends Repository
{
    protected string $table = 'games';

    /** @return array<string,mixed>|null */
    public function findDetailed(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT g.*, p.name AS participant_name, p.city AS participant_city,
                    p.photo_path AS participant_photo, p.registration_no,
                    u.name AS operator_name
             FROM games g
             LEFT JOIN participants p ON p.id = g.participant_id
             LEFT JOIN users u ON u.id = g.operator_id
             WHERE g.id = ? LIMIT 1',
            [$id]
        );
    }

    /** The game currently on air, if any. */
    public function activeGame(): ?array
    {
        return $this->db->selectOne(
            "SELECT g.*, p.name AS participant_name, p.city AS participant_city,
                    p.photo_path AS participant_photo, p.registration_no,
                    u.name AS operator_name
             FROM games g
             LEFT JOIN participants p ON p.id = g.participant_id
             LEFT JOIN users u ON u.id = g.operator_id
             WHERE g.status IN ('pending','running','paused')
             ORDER BY g.id DESC LIMIT 1"
        );
    }

    /** The most recent game of any status - used by the display idle screen. */
    public function latestGame(): ?array
    {
        return $this->db->selectOne(
            'SELECT g.*, p.name AS participant_name, p.city AS participant_city,
                    p.photo_path AS participant_photo, p.registration_no
             FROM games g LEFT JOIN participants p ON p.id = g.participant_id
             ORDER BY g.id DESC LIMIT 1'
        );
    }

    public function generateCode(): string
    {
        do {
            $code = 'GQ' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $exists = (int) ($this->db->scalar('SELECT COUNT(*) FROM games WHERE game_code = ?', [$code]) ?? 0);
        } while ($exists > 0);
        return $code;
    }

    public function bumpVersion(int $gameId): int
    {
        $this->db->run(
            'UPDATE games SET state_version = state_version + 1, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $gameId]
        );
        return (int) ($this->db->scalar('SELECT state_version FROM games WHERE id = ?', [$gameId]) ?? 0);
    }

    /** @return array<int,array<string,mixed>> */
    public function questionsFor(int $gameId): array
    {
        return $this->db->select(
            'SELECT gq.*, q.question_text, q.correct_option, q.explanation, q.difficulty,
                    c.name AS category_name, gift.name AS gift_name,
                    a.selected_option, a.is_correct, a.result, a.time_taken_ms,
                    a.prize_awarded, a.was_overridden, a.answered_at
             FROM game_questions gq
             INNER JOIN questions q ON q.id = gq.question_id
             LEFT JOIN question_categories c ON c.id = q.category_id
             LEFT JOIN gifts gift ON gift.id = gq.gift_id
             LEFT JOIN game_answers a ON a.game_question_id = gq.id
             WHERE gq.game_id = ? ORDER BY gq.level_no ASC',
            [$gameId]
        );
    }

    /** @return array<int,int> Question ids already served in this game. */
    public function servedQuestionIds(int $gameId): array
    {
        $rows = $this->db->select('SELECT question_id FROM game_questions WHERE game_id = ?', [$gameId]);
        return array_map(static fn ($r) => (int) $r['question_id'], $rows);
    }

    /** @return array<int,array<string,mixed>> */
    public function lifelinesUsed(int $gameId): array
    {
        return $this->db->select(
            'SELECT gl.*, l.name AS lifeline_name FROM game_lifelines gl
             INNER JOIN lifelines l ON l.id = gl.lifeline_id
             WHERE gl.game_id = ? ORDER BY gl.id',
            [$gameId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $gameId, int $limit = 200): array
    {
        return $this->db->select(
            'SELECT ge.*, u.name AS user_name FROM game_events ge
             LEFT JOIN users u ON u.id = ge.user_id
             WHERE ge.game_id = ? ORDER BY ge.id DESC LIMIT ' . max(1, $limit),
            [$gameId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function giftsWon(int $gameId): array
    {
        return $this->db->select(
            'SELECT g.id, g.name, g.image_path, g.value_amount, a.level_no
             FROM game_answers a INNER JOIN gifts g ON g.id = a.gift_id
             WHERE a.game_id = ? ORDER BY a.level_no',
            [$gameId]
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(p.name LIKE :search OR g.game_code LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'g.status = :status';
            $bindings['status'] = $filters['status'];
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'g.created_at >= :from';
            $bindings['from'] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'g.created_at <= :to';
            $bindings['to'] = $filters['to'] . ' 23:59:59';
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->scalar(
            'SELECT COUNT(*) FROM games g LEFT JOIN participants p ON p.id = g.participant_id' . $clause,
            $bindings
        ) ?? 0);

        $offset = (max(1, $page) - 1) * $perPage;
        $rows = $this->db->select(
            'SELECT g.*, p.name AS participant_name, p.city AS participant_city, u.name AS operator_name,
                    (SELECT COUNT(*) FROM game_lifelines gl WHERE gl.game_id = g.id) AS lifelines_used,
                    (SELECT COUNT(*) FROM game_answers ga WHERE ga.game_id = g.id AND ga.gift_id IS NOT NULL) AS gifts_won,
                    TIMESTAMPDIFF(SECOND, COALESCE(g.started_at, g.created_at), COALESCE(g.ended_at, NOW())) AS duration_seconds
             FROM games g
             LEFT JOIN participants p ON p.id = g.participant_id
             LEFT JOIN users u ON u.id = g.operator_id'
            . $clause . ' ORDER BY g.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $bindings
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed> */
    public function statistics(): array
    {
        $row = $this->db->selectOne(
            "SELECT
                COUNT(*) AS total_games,
                SUM(status = 'completed') AS completed_games,
                SUM(status IN ('running','paused','pending')) AS active_games,
                COALESCE(SUM(final_prize), 0) AS total_prize,
                COALESCE(AVG(NULLIF(final_prize, 0)), 0) AS average_prize,
                COALESCE(MAX(final_prize), 0) AS highest_prize,
                COALESCE(AVG(questions_attempted), 0) AS average_questions
             FROM games"
        );
        return $row ?? [];
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 8): array
    {
        return $this->db->select(
            'SELECT g.id, g.game_code, g.status, g.final_prize, g.questions_attempted, g.created_at,
                    p.name AS participant_name
             FROM games g LEFT JOIN participants p ON p.id = g.participant_id
             ORDER BY g.id DESC LIMIT ' . max(1, $limit)
        );
    }

    public function logEvent(int $gameId, string $type, ?string $state, ?int $levelNo, array $payload = [], ?int $userId = null): void
    {
        $this->db->insert('game_events', [
            'game_id'    => $gameId,
            'event_type' => substr($type, 0, 60),
            'state'      => $state,
            'level_no'   => $levelNo,
            'payload'    => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
            'user_id'    => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
