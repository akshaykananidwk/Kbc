<?php
declare(strict_types=1);

namespace App\Repositories;

final class PrizeLevelRepository extends Repository
{
    protected string $table = 'prize_levels';

    /** @return array<int,array<string,mixed>> The ladder from level 1 upwards. */
    public function ladder(bool $activeOnly = true): array
    {
        $where = $activeOnly ? " WHERE p.status = 'active'" : '';
        return $this->db->select(
            'SELECT p.*, g.name AS gift_name, g.image_path AS gift_image, g.value_amount AS gift_value
             FROM prize_levels p LEFT JOIN gifts g ON g.id = p.gift_id'
            . $where . ' ORDER BY p.level_no ASC'
        );
    }

    /** @return array<string,mixed>|null */
    public function findByLevel(int $levelNo): ?array
    {
        return $this->db->selectOne(
            'SELECT p.*, g.name AS gift_name, g.image_path AS gift_image, g.value_amount AS gift_value
             FROM prize_levels p LEFT JOIN gifts g ON g.id = p.gift_id
             WHERE p.level_no = ? AND p.status = \'active\' LIMIT 1',
            [$levelNo]
        );
    }

    public function maxLevel(): int
    {
        return (int) ($this->db->scalar("SELECT COALESCE(MAX(level_no), 0) FROM prize_levels WHERE status = 'active'") ?? 0);
    }

    public function amountForLevel(int $levelNo): float
    {
        return (float) ($this->db->scalar(
            "SELECT amount FROM prize_levels WHERE level_no = ? AND status = 'active' LIMIT 1",
            [$levelNo]
        ) ?? 0.0);
    }

    /**
     * The highest guaranteed amount the participant has already banked.
     * A level only counts once it has been answered correctly.
     */
    public function guaranteedAmountUpTo(int $levelNo): float
    {
        if ($levelNo < 1) {
            return 0.0;
        }
        return (float) ($this->db->scalar(
            "SELECT COALESCE(MAX(amount), 0) FROM prize_levels
             WHERE is_guaranteed = 1 AND status = 'active' AND level_no <= ?",
            [$levelNo]
        ) ?? 0.0);
    }

    /** @return array<int,array<string,mixed>> */
    public function guaranteedLevels(): array
    {
        return $this->db->select(
            "SELECT * FROM prize_levels WHERE is_guaranteed = 1 AND status = 'active' ORDER BY level_no"
        );
    }

    public function totalLadderValue(): float
    {
        return (float) ($this->db->scalar("SELECT COALESCE(SUM(amount), 0) FROM prize_levels WHERE status = 'active'") ?? 0.0);
    }

    public function nextLevelNo(): int
    {
        return $this->maxLevel() + 1;
    }

    public function levelNoExists(int $levelNo, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM prize_levels WHERE level_no = ?';
        $bindings = [$levelNo];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $ignoreId;
        }
        return (int) ($this->db->scalar($sql, $bindings) ?? 0) > 0;
    }

    /** Renumber levels 1..N after a deletion so the ladder never has gaps. */
    public function resequence(): void
    {
        $rows = $this->db->select('SELECT id FROM prize_levels ORDER BY level_no ASC, id ASC');
        $this->db->transaction(function () use ($rows): void {
            // Two passes avoid tripping the unique key while shuffling.
            foreach ($rows as $i => $row) {
                $this->db->update('prize_levels', ['level_no' => 10000 + $i], ['id' => (int) $row['id']]);
            }
            foreach ($rows as $i => $row) {
                $this->db->update('prize_levels', ['level_no' => $i + 1], ['id' => (int) $row['id']]);
            }
        });
    }
}
