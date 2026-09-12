<?php
declare(strict_types=1);

namespace App\Repositories;

final class LifelineRepository extends Repository
{
    protected string $table = 'lifelines';

    /** @return array<int,array<string,mixed>> */
    public function enabled(): array
    {
        $rows = $this->db->select('SELECT * FROM lifelines WHERE is_enabled = 1 ORDER BY sort_order, id');
        foreach ($rows as $i => $row) {
            $rows[$i]['config'] = $this->decodeConfig($row['config'] ?? null);
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function allDecoded(): array
    {
        $rows = $this->all('sort_order ASC, id ASC');
        foreach ($rows as $i => $row) {
            $rows[$i]['config'] = $this->decodeConfig($row['config'] ?? null);
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function findByCode(string $code): ?array
    {
        $row = $this->db->selectOne('SELECT * FROM lifelines WHERE code = ? LIMIT 1', [$code]);
        if ($row === null) {
            return null;
        }
        $row['config'] = $this->decodeConfig($row['config'] ?? null);
        return $row;
    }

    /** @return array<string,mixed> */
    public function decodeConfig(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $config */
    public function saveConfig(int $id, array $config): void
    {
        $this->updateById($id, ['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return array<int,array<string,mixed>> */
    public function usageStats(): array
    {
        return $this->db->select(
            'SELECT l.code, l.name, COUNT(gl.id) AS times_used
             FROM lifelines l LEFT JOIN game_lifelines gl ON gl.lifeline_id = l.id
             GROUP BY l.id, l.code, l.name ORDER BY times_used DESC'
        );
    }
}
