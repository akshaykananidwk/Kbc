<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Records every action worth answering "who changed this?" for.
 */
final class AuditService
{
    /** @param array<string,mixed> $meta */
    public static function log(
        string $action,
        string $description = '',
        ?string $entityType = null,
        int|string|null $entityId = null,
        array $meta = []
    ): void {
        try {
            $user = AuthService::user();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = null;
            }

            Database::instance()->insert('audit_logs', [
                'user_id'     => $user === null ? null : (int) $user['id'],
                'user_name'   => $user === null ? 'system' : (string) $user['name'],
                'action'      => substr($action, 0, 80),
                'entity_type' => $entityType === null ? null : substr($entityType, 0, 60),
                'entity_id'   => $entityId === null ? null : (int) $entityId,
                'description' => substr($description, 0, 500),
                'meta'        => $meta === [] ? null : json_encode(self::redact($meta), JSON_UNESCAPED_UNICODE),
                'ip_address'  => $ip,
                'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the request it is describing.
            Logger::warning('Audit log write failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function redact(array $meta): array
    {
        $out = [];
        foreach ($meta as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'password') || str_contains($lower, 'token') || str_contains($lower, 'secret')) {
                $out[$key] = '***redacted***';
                continue;
            }
            $out[$key] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 30): array
    {
        $db = Database::instance();
        $where = [];
        $bindings = [];

        if (($filters['action'] ?? '') !== '') {
            $where[] = 'action = :action';
            $bindings['action'] = $filters['action'];
        }
        if (($filters['user_id'] ?? 0) > 0) {
            $where[] = 'user_id = :user_id';
            $bindings['user_id'] = (int) $filters['user_id'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(description LIKE :search OR user_name LIKE :search OR entity_type LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'created_at >= :from';
            $bindings['from'] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'created_at <= :to';
            $bindings['to'] = $filters['to'] . ' 23:59:59';
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) ($db->scalar('SELECT COUNT(*) FROM audit_logs' . $clause, $bindings) ?? 0);

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows = $db->select(
            'SELECT * FROM audit_logs' . $clause . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $bindings
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,string> */
    public static function actions(): array
    {
        $rows = Database::instance()->select('SELECT DISTINCT action FROM audit_logs ORDER BY action');
        return array_map(static fn ($r) => (string) $r['action'], $rows);
    }
}
