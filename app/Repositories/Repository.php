<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

abstract class Repository
{
    protected string $table = '';
    protected Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM `' . $this->table . '` WHERE id = ? LIMIT 1', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $orderBy = 'id ASC'): array
    {
        return $this->db->select('SELECT * FROM `' . $this->table . '` ORDER BY ' . $this->safeOrder($orderBy));
    }

    public function count(string $where = '', array $bindings = []): int
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->table . '`' . ($where === '' ? '' : ' WHERE ' . $where);
        return (int) ($this->db->scalar($sql, $bindings) ?? 0);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        if ($this->db->columnExists($this->table, 'created_at') && !isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        if ($this->db->columnExists($this->table, 'updated_at') && !isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }
        return $this->db->insert($this->table, $data);
    }

    /** @param array<string,mixed> $data */
    public function updateById(int $id, array $data): int
    {
        if ($this->db->columnExists($this->table, 'updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        return $this->db->update($this->table, $data, ['id' => $id]);
    }

    public function deleteById(int $id): int
    {
        return $this->db->delete($this->table, ['id' => $id]);
    }

    public function db(): Database
    {
        return $this->db;
    }

    /**
     * Order clauses are built from fixed application strings, never from raw
     * request input; this filter is a second line of defence.
     */
    protected function safeOrder(string $orderBy): string
    {
        $parts = [];
        foreach (explode(',', $orderBy) as $chunk) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_.]*)\s*(ASC|DESC)?\s*$/i', $chunk, $m)) {
                $parts[] = $m[1] . ' ' . strtoupper($m[2] ?? 'ASC');
            }
        }
        return $parts === [] ? 'id ASC' : implode(', ', $parts);
    }
}
