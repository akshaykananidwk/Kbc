<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private static string $path = '';

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function write(string $level, string $message, array $context = []): void
    {
        if (self::$path === '') {
            return;
        }
        if (!is_dir(self::$path)) {
            @mkdir(self::$path, 0750, true);
        }
        $file = self::$path . '/app-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . self::encodeContext($context)
        );
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function exception(\Throwable $e): void
    {
        self::write('ERROR', $e->getMessage(), [
            'type' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())[0] ?? '',
        ]);
    }

    /**
     * Secrets are never written to disk.
     *
     * @param array<string,mixed> $context
     */
    private static function encodeContext(array $context): string
    {
        $redactKeys = ['token', 'password', 'secret', 'key', 'authorization', 'github_token'];
        $clean = [];
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            $isSecret = false;
            foreach ($redactKeys as $needle) {
                if (str_contains($lower, $needle)) {
                    $isSecret = true;
                    break;
                }
            }
            $clean[$key] = $isSecret ? '***redacted***' : (is_scalar($value) || $value === null ? $value : json_encode($value));
        }
        return (string) json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<int,string> */
    public static function files(): array
    {
        if (self::$path === '' || !is_dir(self::$path)) {
            return [];
        }
        $files = glob(self::$path . '/*.log') ?: [];
        rsort($files);
        return $files;
    }

    public static function tail(string $file, int $lines = 300): string
    {
        $real = realpath($file);
        $base = realpath(self::$path);
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            return '';
        }
        $content = file($real, FILE_IGNORE_NEW_LINES) ?: [];
        return implode("\n", array_slice($content, -$lines));
    }
}
