<?php
declare(strict_types=1);

namespace App\Repositories;

final class ParticipantRepository extends Repository
{
    protected string $table = 'participants';

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(p.name LIKE :search OR p.mobile LIKE :search OR p.city LIKE :search OR p.registration_no LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'p.status = :status';
            $bindings['status'] = $filters['status'];
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->scalar('SELECT COUNT(*) FROM participants p' . $clause, $bindings) ?? 0);

        $offset = (max(1, $page) - 1) * $perPage;
        $rows = $this->db->select(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM games g WHERE g.participant_id = p.id) AS games_played,
                    (SELECT COALESCE(MAX(g.final_prize), 0) FROM games g WHERE g.participant_id = p.id) AS best_prize
             FROM participants p' . $clause . ' ORDER BY p.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $bindings
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function selectable(): array
    {
        return $this->db->select(
            "SELECT id, name, registration_no, city, photo_path FROM participants
             WHERE status IN ('active','played') ORDER BY name"
        );
    }

    public function nextRegistrationNumber(): string
    {
        $last = (string) ($this->db->scalar(
            "SELECT registration_no FROM participants WHERE registration_no REGEXP '^GQ-[0-9]+$'
             ORDER BY CAST(SUBSTRING(registration_no, 4) AS UNSIGNED) DESC LIMIT 1"
        ) ?? '');
        $next = $last === '' ? 1 : ((int) substr($last, 3)) + 1;
        return sprintf('GQ-%03d', $next);
    }

    public function registrationExists(string $registrationNo, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM participants WHERE registration_no = ?';
        $bindings = [$registrationNo];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $ignoreId;
        }
        return (int) ($this->db->scalar($sql, $bindings) ?? 0) > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function winnings(int $limit = 20): array
    {
        return $this->db->select(
            'SELECT p.id, p.name, p.city, COUNT(g.id) AS games_played,
                    COALESCE(SUM(g.final_prize), 0) AS total_won,
                    COALESCE(MAX(g.final_prize), 0) AS best_prize
             FROM participants p INNER JOIN games g ON g.participant_id = p.id
             GROUP BY p.id, p.name, p.city
             ORDER BY total_won DESC LIMIT ' . max(1, $limit)
        );
    }
}
