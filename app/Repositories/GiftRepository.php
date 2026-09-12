<?php
declare(strict_types=1);

namespace App\Repositories;

final class GiftRepository extends Repository
{
    protected string $table = 'gifts';

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return $this->db->select("SELECT * FROM gifts WHERE status = 'active' ORDER BY sort_order, name");
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
            $where[] = '(g.name LIKE :search OR g.description LIKE :search OR g.serial_code LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'g.status = :status';
            $bindings['status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->scalar('SELECT COUNT(*) FROM gifts g' . $clause, $bindings) ?? 0);

        $offset = (max(1, $page) - 1) * $perPage;
        $rows = $this->db->select(
            'SELECT g.*, (SELECT GROUP_CONCAT(p.level_no ORDER BY p.level_no) FROM prize_levels p WHERE p.gift_id = g.id) AS levels
             FROM gifts g' . $clause . ' ORDER BY g.sort_order, g.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $bindings
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function remaining(int $giftId): int
    {
        $row = $this->find($giftId);
        if ($row === null) {
            return 0;
        }
        return max(0, (int) $row['quantity_total'] - (int) $row['quantity_used']);
    }

    /** Marks one unit of the gift as handed out. Returns false when out of stock. */
    public function consume(int $giftId): bool
    {
        $affected = $this->db->run(
            'UPDATE gifts SET quantity_used = quantity_used + 1, updated_at = ?
             WHERE id = ? AND quantity_used < quantity_total',
            [date('Y-m-d H:i:s'), $giftId]
        )->rowCount();

        if ($affected > 0) {
            $this->db->run(
                "UPDATE gifts SET status = 'out_of_stock' WHERE id = ? AND quantity_used >= quantity_total AND status = 'active'",
                [$giftId]
            );
            return true;
        }
        return false;
    }

    public function totalValueAwarded(): float
    {
        return (float) ($this->db->scalar(
            'SELECT COALESCE(SUM(g.value_amount), 0) FROM game_answers a INNER JOIN gifts g ON g.id = a.gift_id'
        ) ?? 0.0);
    }

    /** @return array<int,array<string,mixed>> */
    public function distribution(): array
    {
        return $this->db->select(
            'SELECT g.id, g.name, g.value_amount, g.quantity_total, g.quantity_used,
                    COUNT(a.id) AS times_awarded
             FROM gifts g LEFT JOIN game_answers a ON a.gift_id = g.id
             GROUP BY g.id, g.name, g.value_amount, g.quantity_total, g.quantity_used
             ORDER BY times_awarded DESC, g.name'
        );
    }
}
