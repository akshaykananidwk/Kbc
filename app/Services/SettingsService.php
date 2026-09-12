<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\Crypto;

/**
 * Typed access to the settings table with a per-request cache.
 */
final class SettingsService
{
    /** @var array<string,array{value:mixed,type:string,is_secret:bool}>|null */
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return array<string,array{value:mixed,type:string,is_secret:bool}> */
    private static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        try {
            $rows = Database::instance()->select('SELECT key_name, value, type, is_secret FROM settings');
        } catch (\Throwable) {
            return self::$cache;
        }

        foreach ($rows as $row) {
            self::$cache[(string) $row['key_name']] = [
                'value'     => $row['value'],
                'type'      => (string) $row['type'],
                'is_secret' => (bool) $row['is_secret'],
            ];
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (!isset($all[$key])) {
            return $default;
        }
        $row = $all[$key];
        $value = $row['value'];

        if ($value === null || $value === '') {
            return match ($row['type']) {
                'boolean' => $default ?? false,
                'integer' => $default ?? 0,
                'float'   => $default ?? 0.0,
                'json'    => $default ?? [],
                default   => $default ?? '',
            };
        }

        if ($row['is_secret'] && Crypto::isEncrypted((string) $value)) {
            $value = Crypto::decrypt((string) $value);
        }

        return match ($row['type']) {
            'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true),
            'integer' => (int) $value,
            'float'   => (float) $value,
            'json'    => json_decode((string) $value, true) ?? ($default ?? []),
            default   => (string) $value,
        };
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        return is_bool($value) ? $value : in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    /** Raw stored value - used by the admin form so secrets stay masked. */
    public static function isSecret(string $key): bool
    {
        return self::all()[$key]['is_secret'] ?? false;
    }

    public static function hasValue(string $key): bool
    {
        $all = self::all();
        return isset($all[$key]) && $all[$key]['value'] !== null && $all[$key]['value'] !== '';
    }

    public static function set(string $key, mixed $value, ?string $type = null, ?string $group = null): void
    {
        $db = Database::instance();
        $existing = $db->selectOne('SELECT id, type, is_secret FROM settings WHERE key_name = ? LIMIT 1', [$key]);

        $type = $type ?? (string) ($existing['type'] ?? 'string');
        $isSecret = (bool) ($existing['is_secret'] ?? false);

        if (is_bool($value)) {
            $stored = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $stored = (string) json_encode($value, JSON_UNESCAPED_UNICODE);
            $type = 'json';
        } else {
            $stored = (string) $value;
        }

        if ($isSecret && $stored !== '' && !Crypto::isEncrypted($stored)) {
            $stored = Crypto::encrypt($stored);
        }

        $now = date('Y-m-d H:i:s');
        if ($existing === null) {
            $db->insert('settings', [
                'group_name' => $group ?? 'general',
                'key_name'   => $key,
                'value'      => $stored,
                'type'       => $type,
                'is_secret'  => $isSecret ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $db->update('settings', ['value' => $stored, 'type' => $type, 'updated_at' => $now], ['id' => (int) $existing['id']]);
        }

        self::flush();
    }

    /** @param array<string,mixed> $values */
    public static function setMany(array $values): void
    {
        Database::instance()->transaction(static function () use ($values): void {
            foreach ($values as $key => $value) {
                self::set($key, $value);
            }
        });
    }

    /** @return array<int,array<string,mixed>> */
    public static function group(string $group): array
    {
        return Database::instance()->select(
            'SELECT * FROM settings WHERE group_name = ? ORDER BY sort_order, id',
            [$group]
        );
    }

    /** @return array<int,string> */
    public static function groups(): array
    {
        $rows = Database::instance()->select('SELECT DISTINCT group_name FROM settings ORDER BY group_name');
        return array_map(static fn ($r) => (string) $r['group_name'], $rows);
    }

    public static function currency(): string
    {
        return self::string('currency_symbol', '₹');
    }

    public static function money(float|int|string $amount): string
    {
        return \App\Support\Money::format($amount, self::currency(), self::bool('indian_number_format', true));
    }
}
