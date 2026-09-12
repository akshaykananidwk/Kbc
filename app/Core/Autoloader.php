<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-4 style autoloader. The application intentionally ships without
 * Composer so that it can be uploaded to any shared Apache host as-is.
 */
final class Autoloader
{
    /** @var array<string,string> */
    private array $prefixes = [];

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $this->prefixes[trim($prefix, '\\') . '\\'] = rtrim($baseDir, '/') . '/';
    }

    public function load(string $class): void
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
}
