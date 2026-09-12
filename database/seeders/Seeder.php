<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

abstract class Seeder
{
    protected string $now;

    public function __construct(protected Database $db)
    {
        $this->now = date('Y-m-d H:i:s');
    }

    abstract public function run(): void;

    /**
     * Insert a row only when the unique key is not present yet, so seeders
     * are safe to re-run.
     *
     * @param array<string,mixed> $match
     * @param array<string,mixed> $data
     */
    protected function firstOrCreate(string $table, array $match, array $data): int
    {
        $conditions = [];
        foreach (array_keys($match) as $column) {
            $conditions[] = '`' . $column . '` = :' . $column;
        }
        $sql = 'SELECT id FROM `' . $table . '` WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1';
        $existing = $this->db->selectOne($sql, $match);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return $this->db->insert($table, array_merge($match, $data));
    }
}
