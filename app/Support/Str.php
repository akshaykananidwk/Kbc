<?php
declare(strict_types=1);

namespace App\Support;

final class Str
{
    public static function slug(string $value, string $separator = '-'): string
    {
        $original = trim($value);
        $value = $original;

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && trim($ascii) !== '') {
            $value = $ascii;
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? '';
        $value = trim($value, $separator);

        if ($value !== '') {
            return $value;
        }

        // Gujarati, Hindi and other non-Latin names have no ASCII form, so the
        // slug is derived from the text itself. It must be stable: a random
        // one made every save look like a brand new category.
        return $original === ''
            ? 'item'
            : 'item-' . substr(md5($original), 0, 10);
    }

    public static function random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    public static function mask(string $value, int $visible = 4): string
    {
        if ($value === '') {
            return '';
        }
        $len = strlen($value);
        if ($len <= $visible) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, $visible) . str_repeat('*', min(16, $len - $visible));
    }

    /** Escape for safe HTML output. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function humanBytes(int|float $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max((float) $bytes, 0);
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        return round($bytes / (1024 ** $power), $decimals) . ' ' . $units[$power];
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
