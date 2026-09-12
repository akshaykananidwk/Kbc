<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\Database;
use App\Core\Logger;
use RuntimeException;

/**
 * Versioned migration runner with a history table. Only PHP migration
 * classes are executed - raw .sql files are never blindly imported.
 */
final class MigrationService
{
    private const TABLE = 'migrations';

    public function __construct(private Database $db)
    {
    }

    public static function make(?Database $db = null): self
    {
        return new self($db ?? Database::instance());
    }

    public function ensureTable(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT UNSIGNED NOT NULL DEFAULT 1,
                `ran_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<int,string> */
    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->db->select('SELECT migration FROM `' . self::TABLE . '` ORDER BY id');
        return array_map(static fn ($r) => (string) $r['migration'], $rows);
    }

    /** @return array<int,string> Absolute paths of all migration files, sorted by name. */
    public function available(): array
    {
        $dir = Application::instance()->rootPath('database/migrations');
        $files = glob($dir . '/*.php') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    /** @return array<int,string> Names of migrations that have not run yet. */
    public function pending(): array
    {
        $applied = $this->applied();
        $pending = [];
        foreach ($this->available() as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    /**
     * Run every pending migration inside its own transaction where possible.
     *
     * @return array<int,string> Names of migrations that were executed.
     */
    public function migrate(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $batch = (int) ($this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM `' . self::TABLE . '`') ?? 0) + 1;
        $ran = [];

        foreach ($this->available() as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $applied, true)) {
                continue;
            }

            $migration = $this->instantiate($file, $name);

            try {
                $migration->up($this->db);
            } catch (\Throwable $e) {
                Logger::error('Migration failed: ' . $name, ['error' => $e->getMessage()]);
                throw new RuntimeException('Migration "' . $name . '" failed: ' . $e->getMessage(), 0, $e);
            }

            $this->db->insert(self::TABLE, [
                'migration' => $name,
                'batch'     => $batch,
                'ran_at'    => date('Y-m-d H:i:s'),
            ]);
            $ran[] = $name;
        }

        return $ran;
    }

    /** Roll back the most recent batch. */
    public function rollbackLastBatch(): array
    {
        $this->ensureTable();
        $batch = (int) ($this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM `' . self::TABLE . '`') ?? 0);
        if ($batch === 0) {
            return [];
        }
        $rows = $this->db->select(
            'SELECT migration FROM `' . self::TABLE . '` WHERE batch = ? ORDER BY id DESC',
            [$batch]
        );
        $dir = Application::instance()->rootPath('database/migrations');
        $rolled = [];
        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            $file = $dir . '/' . $name . '.php';
            if (!is_file($file)) {
                continue;
            }
            $migration = $this->instantiate($file, $name);
            $migration->down($this->db);
            $this->db->delete(self::TABLE, ['migration' => $name]);
            $rolled[] = $name;
        }
        return $rolled;
    }

    /**
     * Migration files return an anonymous class implementing up()/down().
     * Nothing else from the file is executed.
     */
    private function instantiate(string $file, string $name): object
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Migration file is unreadable: ' . $name);
        }
        $instance = require $file;
        if (!is_object($instance) || !method_exists($instance, 'up') || !method_exists($instance, 'down')) {
            throw new RuntimeException('Migration "' . $name . '" must return an object with up() and down() methods.');
        }
        return $instance;
    }
}
