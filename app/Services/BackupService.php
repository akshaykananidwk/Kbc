<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Database and file backups.
 *
 * The SQL dump is produced in pure PHP through PDO so it works on shared
 * hosting where exec()/mysqldump are unavailable.
 */
final class BackupService
{
    public function __construct(private Database $db)
    {
    }

    public static function make(?Database $db = null): self
    {
        return new self($db ?? Database::instance());
    }

    public function directory(): string
    {
        $dir = Application::instance()->storagePath('backups');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('The storage/backups folder is not writable.');
        }
        return $dir;
    }

    /**
     * @return array<string,mixed>
     */
    public function backupDatabase(?int $userId = null, string $note = ''): array
    {
        $directory = $this->directory();
        $filename = $this->uniqueFilename('db');
        $zipPath = $directory . '/' . $filename;
        $sqlPath = $directory . '/tmp-' . Str::random(8) . '.sql';

        $recordId = $this->record($filename, $zipPath, 'database', $userId, $note);

        try {
            $this->writeSqlDump($sqlPath);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the backup archive.');
            }
            $zip->addFile($sqlPath, 'database.sql');
            $zip->addFromString('manifest.json', (string) json_encode([
                'type'       => 'database',
                'database'   => $this->db->databaseName(),
                'app_version'=> (string) Config::get('app.version', '1.0.0'),
                'created_at' => date('c'),
                'tables'     => $this->db->tables(),
            ], JSON_PRETTY_PRINT));
            $zip->close();

            @unlink($sqlPath);

            $size = (int) (filesize($zipPath) ?: 0);
            $this->db->update('backups', ['status' => 'completed', 'size_bytes' => $size], ['id' => $recordId]);

            AuditService::log('backup.created', 'Created database backup ' . $filename, 'backup', $recordId);

            return [
                'id'          => $recordId,
                'filename'    => $filename,
                'path'        => $zipPath,
                'size'        => $size,
                'size_human'  => Str::humanBytes($size),
                'type'        => 'database',
            ];
        } catch (\Throwable $e) {
            @unlink($sqlPath);
            @unlink($zipPath);
            $this->db->update('backups', ['status' => 'failed', 'note' => substr($e->getMessage(), 0, 300)], ['id' => $recordId]);
            Logger::error('Database backup failed: ' . $e->getMessage());
            throw new RuntimeException('Database backup failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<int,string> $extraExcludes
     * @return array<string,mixed>
     */
    public function backupFiles(?int $userId = null, string $note = '', array $extraExcludes = []): array
    {
        $filename = $this->uniqueFilename('files');
        $zipPath = $this->directory() . '/' . $filename;

        $recordId = $this->record($filename, $zipPath, 'files', $userId, $note);

        try {
            $root = Application::instance()->rootPath();
            $excludes = array_merge([
                'storage/backups',
                'storage/cache',
                'storage/tmp',
                '.git',
                'node_modules',
            ], $extraExcludes);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the backup archive.');
            }

            $count = $this->addDirectoryToZip($zip, $root, $root, $excludes);

            $zip->addFromString('manifest.json', (string) json_encode([
                'type'        => 'files',
                'app_version' => (string) Config::get('app.version', '1.0.0'),
                'created_at'  => date('c'),
                'file_count'  => $count,
            ], JSON_PRETTY_PRINT));
            $zip->close();

            $size = (int) (filesize($zipPath) ?: 0);
            $this->db->update('backups', ['status' => 'completed', 'size_bytes' => $size], ['id' => $recordId]);

            AuditService::log('backup.created', 'Created files backup ' . $filename . ' (' . $count . ' files)', 'backup', $recordId);

            return [
                'id'         => $recordId,
                'filename'   => $filename,
                'path'       => $zipPath,
                'size'       => $size,
                'size_human' => Str::humanBytes($size),
                'type'       => 'files',
                'file_count' => $count,
            ];
        } catch (\Throwable $e) {
            @unlink($zipPath);
            $this->db->update('backups', ['status' => 'failed', 'note' => substr($e->getMessage(), 0, 300)], ['id' => $recordId]);
            Logger::error('File backup failed: ' . $e->getMessage());
            throw new RuntimeException('File backup failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Restore a database backup. The current database is dumped first so a
     * bad restore can still be undone.
     *
     * @return array{tables:int,statements:int,safety_backup:string}
     */
    public function restoreDatabase(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            throw new RuntimeException('That backup file no longer exists.');
        }

        $safety = $this->backupDatabase(null, 'Automatic safety backup taken before a restore');

        $extractDir = Application::instance()->storagePath('tmp/restore-' . Str::random(8));
        if (!@mkdir($extractDir, 0750, true) && !is_dir($extractDir)) {
            throw new RuntimeException('Could not create a temporary folder for the restore.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('The backup archive could not be opened.');
            }
            $zip->extractTo($extractDir);
            $zip->close();

            $sqlFile = $extractDir . '/database.sql';
            if (!is_file($sqlFile)) {
                throw new RuntimeException('This archive does not contain a database dump.');
            }

            $result = $this->importSqlFile($sqlFile);

            SettingsService::flush();
            CacheService::clearAll();

            AuditService::log('backup.restored', 'Restored the database from ' . basename($zipPath), 'backup');

            return [
                'tables'        => $result['tables'],
                'statements'    => $result['statements'],
                'safety_backup' => $safety['filename'],
            ];
        } finally {
            $this->removeDirectory($extractDir);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listBackups(): array
    {
        $rows = $this->db->select('SELECT b.*, u.name AS created_by_name FROM backups b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC');
        foreach ($rows as $i => $row) {
            $rows[$i]['exists'] = is_file((string) $row['path']);
            $rows[$i]['size_human'] = Str::humanBytes((int) $row['size_bytes']);
        }
        return $rows;
    }

    public function delete(int $id): bool
    {
        $row = $this->db->selectOne('SELECT * FROM backups WHERE id = ?', [$id]);
        if ($row === null) {
            return false;
        }
        // Only ever unlink inside the backups directory.
        $real = realpath((string) $row['path']);
        $base = realpath($this->directory());
        if ($real !== false && $base !== false && str_starts_with($real, $base)) {
            @unlink($real);
        }
        $this->db->delete('backups', ['id' => $id]);
        AuditService::log('backup.deleted', 'Deleted backup ' . $row['filename'], 'backup', $id);
        return true;
    }

    /** Resolve a backup path safely for download. */
    public function pathFor(int $id): ?string
    {
        $row = $this->db->selectOne('SELECT path FROM backups WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }
        $real = realpath((string) $row['path']);
        $base = realpath($this->directory());
        if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
            return null;
        }
        return $real;
    }

    // -----------------------------------------------------------------

    /**
     * Backup names are unique even when two backups are taken inside the
     * same second (which happens when a restore takes its safety copy).
     */
    private function uniqueFilename(string $suffix): string
    {
        $base = 'backup_' . date('Y-m-d_H-i-s');
        $attempt = 0;

        while (true) {
            $candidate = $base . ($attempt === 0 ? '' : '-' . $attempt) . '_' . $suffix . '.zip';
            $onDisk = is_file($this->directory() . '/' . $candidate);
            $inDatabase = (int) ($this->db->scalar(
                'SELECT COUNT(*) FROM backups WHERE filename = ?',
                [$candidate]
            ) ?? 0) > 0;

            if (!$onDisk && !$inDatabase) {
                return $candidate;
            }
            $attempt++;
            if ($attempt > 500) {
                return $base . '-' . Str::random(6) . '_' . $suffix . '.zip';
            }
        }
    }

    private function record(string $filename, string $path, string $type, ?int $userId, string $note): int
    {
        return $this->db->insert('backups', [
            'filename'   => $filename,
            'path'       => $path,
            'type'       => $type,
            'status'     => 'running',
            'note'       => $note === '' ? null : substr($note, 0, 300),
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Write a complete, importable SQL dump using only PDO. */
    private function writeSqlDump(string $target): void
    {
        $handle = @fopen($target, 'w');
        if ($handle === false) {
            throw new RuntimeException('Could not write the SQL dump file.');
        }

        fwrite($handle, "-- Ganpati Bapa Quiz Show database backup\n");
        fwrite($handle, '-- Generated: ' . date('c') . "\n");
        fwrite($handle, '-- Database: ' . $this->db->databaseName() . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        foreach ($this->db->tables() as $table) {
            $create = $this->db->selectOne('SHOW CREATE TABLE `' . $this->safeTable($table) . '`');
            $createSql = (string) ($create['Create Table'] ?? '');

            fwrite($handle, "\n-- ---------------------------------------------------------\n");
            fwrite($handle, '-- Table: ' . $table . "\n");
            fwrite($handle, "-- ---------------------------------------------------------\n");
            fwrite($handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
            fwrite($handle, $createSql . ";\n\n");

            $statement = $this->db->pdo()->query('SELECT * FROM `' . $this->safeTable($table) . '`');
            if ($statement === false) {
                continue;
            }

            $batch = [];
            $columns = null;
            while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                if ($columns === null) {
                    $columns = array_keys($row);
                }
                $values = [];
                foreach ($row as $value) {
                    $values[] = $this->quoteValue($value);
                }
                $batch[] = '(' . implode(',', $values) . ')';

                if (count($batch) >= 100) {
                    $this->writeInsert($handle, $table, $columns, $batch);
                    $batch = [];
                }
            }
            if ($batch !== [] && $columns !== null) {
                $this->writeInsert($handle, $table, $columns, $batch);
            }
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    /**
     * @param resource $handle
     * @param array<int,string> $columns
     * @param array<int,string> $batch
     */
    private function writeInsert($handle, string $table, array $columns, array $batch): void
    {
        $columnList = implode(', ', array_map(static fn ($c) => '`' . $c . '`', $columns));
        fwrite($handle, 'INSERT INTO `' . $table . '` (' . $columnList . ") VALUES\n");
        fwrite($handle, implode(",\n", $batch) . ";\n");
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return $this->db->pdo()->quote((string) $value);
    }

    /**
     * Execute a dump file statement by statement.
     *
     * @return array{tables:int,statements:int}
     */
    private function importSqlFile(string $file): array
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException('The dump file could not be read.');
        }

        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $buffer = '';
        $statements = 0;
        $tables = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*!')) {
                    continue;
                }
                $buffer .= $line;

                // Statements in our own dumps always end with ";\n".
                if (str_ends_with($trimmed, ';')) {
                    $sql = trim($buffer);
                    $buffer = '';
                    if ($sql === '') {
                        continue;
                    }
                    if (stripos($sql, 'CREATE TABLE') === 0) {
                        $tables++;
                    }
                    $pdo->exec($sql);
                    $statements++;
                }
            }
        } finally {
            fclose($handle);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return ['tables' => $tables, 'statements' => $statements];
    }

    /** @param array<int,string> $excludes */
    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $root, array $excludes): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $path = $item->getPathname();
            $relative = ltrim(str_replace($root, '', $path), '/');

            foreach ($excludes as $exclude) {
                if ($relative === $exclude || str_starts_with($relative, rtrim($exclude, '/') . '/')) {
                    continue 2;
                }
            }

            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
            } elseif ($item->isFile()) {
                $zip->addFile($path, $relative);
                $count++;
            }
        }

        return $count;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }

    private function safeTable(string $table): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $table) ?? '';
    }
}
