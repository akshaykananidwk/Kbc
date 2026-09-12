<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;

/**
 * Simple file cache. Clearing it never touches uploads, storage/backups or
 * any user data - only storage/cache and storage/tmp.
 */
final class CacheService
{
    public static function path(string $key): string
    {
        return Application::instance()->storagePath('cache/' . sha1($key) . '.cache');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return $default;
        }
        $payload = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        if (!is_array($payload) || !isset($payload['expires'], $payload['value'])) {
            return $default;
        }
        if ($payload['expires'] > 0 && $payload['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $payload['value'];
    }

    public static function put(string $key, mixed $value, int $ttlSeconds = 300): void
    {
        $dir = Application::instance()->storagePath('cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents(self::path($key), serialize([
            'expires' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
            'value'   => $value,
        ]), LOCK_EX);
    }

    public static function forget(string $key): void
    {
        @unlink(self::path($key));
    }

    /**
     * Clear application, view and temporary caches.
     * Explicitly does NOT delete uploads, backups or the database.
     */
    public static function clearAll(): int
    {
        $app = Application::instance();
        $removed = 0;

        foreach (['cache', 'tmp'] as $folder) {
            $removed += self::clearDirectory($app->storagePath($folder));
        }

        SettingsService::flush();

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return $removed;
    }

    private static function clearDirectory(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }
        $removed = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->getFilename() === '.gitignore' || $item->getFilename() === '.htaccess') {
                continue;
            }
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } elseif (@unlink($item->getPathname())) {
                $removed++;
            }
        }
        return $removed;
    }
}
