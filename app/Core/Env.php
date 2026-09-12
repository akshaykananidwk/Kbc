<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Very small .env reader/writer. Values are kept out of $_ENV/getenv() so that
 * they can never leak through phpinfo() style output.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];
    private static bool $loaded = false;
    private static string $path = '';

    public static function load(string $path): void
    {
        self::$path = $path;
        self::$values = [];
        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if (strlen($value) >= 2) {
                $first = $value[0];
                if (($first === '"' || $first === "'") && str_ends_with($value, $first)) {
                    $value = substr($value, 1, -1);
                    if ($first === '"') {
                        $value = str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $value);
                    }
                }
            }
            self::$values[$key] = $value;
        }
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, self::$values)) {
            return $default;
        }
        $value = self::$values[$key];
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$values);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::$values;
    }

    /**
     * Write (or rewrite) an .env file. Existing keys keep their position,
     * new keys are appended.
     *
     * @param array<string,scalar|null> $data
     */
    public static function write(string $path, array $data): bool
    {
        $existing = is_file($path) ? file_get_contents($path) : '';
        $lines = $existing === '' ? [] : preg_split('/\R/', $existing);
        $handled = [];

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $pos));
            if (array_key_exists($key, $data)) {
                $lines[$i] = $key . '=' . self::quote((string) $data[$key]);
                $handled[$key] = true;
            }
        }

        foreach ($data as $key => $value) {
            if (!isset($handled[$key])) {
                $lines[] = $key . '=' . self::quote((string) $value);
            }
        }

        $content = implode("\n", $lines);
        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        $ok = @file_put_contents($path, $content, LOCK_EX) !== false;
        if ($ok) {
            @chmod($path, 0640);
            self::load($path);
        }
        return $ok;
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/[\s"\'#=]/', $value)) {
            return '"' . str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value) . '"';
        }
        return $value;
    }
}
