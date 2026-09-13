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
 * One-click GitHub updater.
 *
 * Sequence: check -> confirm -> backup -> download -> validate -> stage ->
 * replace (protected paths untouched) -> migrate -> clear cache -> done.
 * Any failure after the backup triggers an automatic rollback.
 */
final class UpdateService
{
    private Database $db;
    private ?int $updateId = null;
    /** @var array<int,string> */
    private array $log = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public static function make(?Database $db = null): self
    {
        return new self($db);
    }

    // -----------------------------------------------------------------
    // Check
    // -----------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    public function checkForUpdate(bool $useCache = false): array
    {
        $client = GitHubClient::fromSettings();

        $result = [
            'configured'       => $client->isConfigured(),
            'repository'       => $client->isConfigured() ? $client->repository() : '',
            'branch'           => $client->branch(),
            'has_token'        => $client->hasToken(),
            'current_version'  => $this->currentVersion(),
            'current_commit'   => SettingsService::string('current_commit_sha'),
            'latest_version'   => $this->currentVersion(),
            'latest_commit'    => '',
            'commit_message'   => '',
            'commit_author'    => '',
            'commit_date'      => '',
            'commit_url'       => '',
            'changed_files'    => [],
            'release'          => null,
            'update_available' => false,
            'checked_at'       => date('Y-m-d H:i:s'),
        ];

        if (!$client->isConfigured()) {
            $result['message'] = 'Set the GitHub owner and repository in Settings before checking for updates.';
            return $result;
        }

        if ($useCache) {
            $cached = CacheService::get('update_check');
            if (is_array($cached)) {
                return $cached;
            }
        }

        $commit = $client->latestCommit();
        $release = $client->latestRelease();

        $result['latest_commit']  = $commit['sha'];
        $result['commit_message'] = trim(explode("\n", $commit['message'])[0] ?? '');
        $result['commit_author']  = $commit['author'];
        $result['commit_date']    = $commit['date'] === '' ? '' : date('Y-m-d H:i:s', strtotime($commit['date']));
        $result['commit_url']     = $commit['url'];
        $result['changed_files']  = array_slice($commit['files'], 0, 50);
        $result['release']        = $release;

        if ($release !== null) {
            $result['latest_version'] = ltrim($release['tag'], 'vV');
        } else {
            $result['latest_version'] = substr($commit['sha'], 0, 7);
        }

        $currentCommit = SettingsService::string('current_commit_sha');
        $result['update_available'] = $currentCommit === ''
            ? true
            : !hash_equals($currentCommit, $commit['sha']);

        if ($release !== null && $currentCommit !== '') {
            // A newer release tag also counts as an update.
            $result['update_available'] = $result['update_available']
                || version_compare($result['latest_version'], $this->currentVersion(), '>');
        }

        $result['message'] = $result['update_available']
            ? 'An update is available.'
            : 'You are running the latest version.';

        SettingsService::set('last_checked_at', $result['checked_at']);
        CacheService::put('update_check', $result, 300);

        AuditService::log('update.checked', 'Checked GitHub for updates: ' . $result['message'], 'update');

        return $result;
    }

    // -----------------------------------------------------------------
    // Update
    // -----------------------------------------------------------------

    /**
     * Run the whole update. Returns the update-log row.
     *
     * @return array<string,mixed>
     */
    public function runUpdate(?int $userId = null): array
    {
        $client = GitHubClient::fromSettings();
        if (!$client->isConfigured()) {
            throw new RuntimeException('Configure the GitHub repository in Settings before updating.');
        }

        $check = $this->checkForUpdate(false);
        $app = Application::instance();

        $this->log = [];
        $this->updateId = $this->db->insert('system_updates', [
            'old_version'    => $this->currentVersion(),
            'new_version'    => $check['latest_version'],
            'commit_sha'     => $check['latest_commit'],
            'commit_message' => substr($check['commit_message'], 0, 500),
            'commit_date'    => $check['commit_date'] !== '' ? $check['commit_date'] : null,
            'commit_author'  => $check['commit_author'],
            'status'         => 'checking',
            'initiated_by'   => $userId,
            'started_at'     => date('Y-m-d H:i:s'),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->note('Update started for ' . $client->repository() . '@' . $client->branch());

        $workDir = $app->storagePath('tmp/update-' . Str::random(10));
        $zipPath = $workDir . '/package.zip';
        $extractDir = $workDir . '/extracted';
        $backupDir = null;
        $databaseBackup = null;

        try {
            if (!@mkdir($workDir, 0750, true) && !is_dir($workDir)) {
                throw new RuntimeException('Could not create a temporary working folder. Check that storage/tmp is writable.');
            }

            // --- Step 1: verify ------------------------------------------------
            $this->status('checking');
            $client->verifyRepository();
            $client->verifyBranch();
            $this->note('Repository and branch verified.');

            // --- Step 2: backup ------------------------------------------------
            $this->status('backing_up');
            $backups = BackupService::make($this->db);
            $databaseBackup = $backups->backupDatabase($userId, 'Automatic backup before update');
            $this->note('Database backed up to ' . $databaseBackup['filename'] . ' (' . $databaseBackup['size_human'] . ')');

            $backupDir = $app->storagePath('backups/' . date('Y-m-d_His') . '_pre-update');
            $this->snapshotApplicationFiles($backupDir);
            $this->note('Application files snapshotted to ' . basename($backupDir));

            $this->db->update('system_updates', [
                'backup_status' => 'completed',
                'backup_path'   => $backupDir,
                'updated_at'    => date('Y-m-d H:i:s'),
            ], ['id' => $this->updateId]);

            // --- Step 3: download ----------------------------------------------
            $this->status('downloading');
            $bytes = $client->downloadZipball($zipPath, $check['latest_commit'] !== '' ? $check['latest_commit'] : null);
            $this->note('Downloaded ' . Str::humanBytes($bytes) . ' from GitHub.');

            // --- Step 4: validate + extract --------------------------------------
            $packageRoot = $this->validateAndExtract($zipPath, $extractDir);
            $this->note('Package validated and extracted.');

            // --- Step 5: replace application files ------------------------------
            $this->status('updating');
            $changed = $this->applyFiles($packageRoot);
            $this->note($changed . ' file(s) updated. Protected paths were left untouched.');
            if ($this->writeWarnings !== []) {
                $this->note(
                    'Your host would not let PHP write ' . implode(', ', $this->writeWarnings)
                    . '. The update finished; upload that file by FTP if you need the settings in it.'
                );
            }
            $this->db->update('system_updates', [
                'files_changed' => $changed,
                'updated_at'    => date('Y-m-d H:i:s'),
            ], ['id' => $this->updateId]);

            // --- Step 6: migrations ---------------------------------------------
            $this->status('migrating');
            $ran = MigrationService::make($this->db)->migrate();
            $this->note($ran === [] ? 'No pending migrations.' : 'Ran migrations: ' . implode(', ', $ran));
            $this->db->update('system_updates', [
                'migration_status' => 'completed',
                'migrations_run'   => $ran === [] ? null : implode(',', $ran),
                'updated_at'       => date('Y-m-d H:i:s'),
            ], ['id' => $this->updateId]);

            // --- Step 7: clear caches ---------------------------------------------
            $this->status('clearing_cache');
            $cleared = CacheService::clearAll();
            $this->note('Cleared ' . $cleared . ' cache entr(ies). Uploads, storage and the database were not touched.');

            // --- Step 8: record the new version -----------------------------------
            SettingsService::set('current_commit_sha', $check['latest_commit']);
            SettingsService::set('current_version', $check['latest_version']);

            $this->status('completed');
            $this->db->update('system_updates', [
                'finished_at' => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ], ['id' => $this->updateId]);

            AuditService::log(
                'update.completed',
                'Updated to ' . $check['latest_version'] . ' (' . substr($check['latest_commit'], 0, 7) . ')',
                'update',
                $this->updateId
            );

            return $this->currentRecord();
        } catch (\Throwable $e) {
            Logger::error('Update failed: ' . $e->getMessage());
            $this->note('ERROR: ' . $e->getMessage());

            $rollbackStatus = 'not_required';
            if ($backupDir !== null && is_dir($backupDir)) {
                try {
                    $restored = $this->rollbackFiles($backupDir);
                    $this->note('Rolled back ' . $restored . ' file(s) from the pre-update snapshot.');
                    $rollbackStatus = 'completed';
                } catch (\Throwable $rollbackError) {
                    $this->note('ROLLBACK ERROR: ' . $rollbackError->getMessage());
                    $rollbackStatus = 'failed';
                }
            }

            CacheService::clearAll();

            if ($this->updateId !== null) {
                $this->db->update('system_updates', [
                    'status'          => $rollbackStatus === 'completed' ? 'rolled_back' : 'failed',
                    'rollback_status' => $rollbackStatus,
                    'error_message'   => substr($e->getMessage(), 0, 2000),
                    'log'             => implode("\n", $this->log),
                    'finished_at'     => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s'),
                ], ['id' => $this->updateId]);
            }

            AuditService::log('update.failed', 'Update failed: ' . substr($e->getMessage(), 0, 200), 'update', $this->updateId);

            throw new RuntimeException(
                'The update failed and was rolled back: ' . $e->getMessage()
                . ($databaseBackup !== null ? ' A database backup (' . $databaseBackup['filename'] . ') is available if you need it.' : ''),
                0,
                $e
            );
        } finally {
            $this->removeDirectory($workDir);
        }
    }

    /** Manually roll back to the snapshot taken by a given update run. */
    public function rollback(int $updateId): int
    {
        $row = $this->db->selectOne('SELECT * FROM system_updates WHERE id = ?', [$updateId]);
        if ($row === null) {
            throw new RuntimeException('That update record could not be found.');
        }
        $backupPath = (string) ($row['backup_path'] ?? '');
        if ($backupPath === '' || !is_dir($backupPath)) {
            throw new RuntimeException('No file snapshot is available for that update.');
        }

        $restored = $this->rollbackFiles($backupPath);
        CacheService::clearAll();

        $this->db->update('system_updates', [
            'status'          => 'rolled_back',
            'rollback_status' => 'completed',
            'updated_at'      => date('Y-m-d H:i:s'),
        ], ['id' => $updateId]);

        AuditService::log('update.rolled_back', 'Rolled back update #' . $updateId . ' (' . $restored . ' files restored)', 'update', $updateId);

        return $restored;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $limit = 50): array
    {
        return $this->db->select(
            'SELECT s.*, u.name AS initiated_by_name FROM system_updates s
             LEFT JOIN users u ON u.id = s.initiated_by
             ORDER BY s.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT s.*, u.name AS initiated_by_name FROM system_updates s
             LEFT JOIN users u ON u.id = s.initiated_by WHERE s.id = ?',
            [$id]
        );
    }

    public function currentVersion(): string
    {
        $stored = SettingsService::string('current_version');
        return $stored !== '' ? $stored : (string) Config::get('app.version', '1.0.0');
    }

    /** @return array<int,string> */
    public function protectedPaths(): array
    {
        $paths = Config::get('updates.protected_paths', []);
        return is_array($paths) ? array_values(array_map('strval', $paths)) : [];
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Open the zip, make sure it looks like this application, and extract it.
     * Returns the directory holding the application root inside the package.
     */
    private function validateAndExtract(string $zipPath, string $extractDir): string
    {
        if (!is_file($zipPath) || filesize($zipPath) < 1024) {
            throw new RuntimeException('The downloaded package is missing or too small to be valid.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The downloaded package is not a readable ZIP archive.');
        }

        // Reject archives containing traversal or absolute entries.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
                $zip->close();
                throw new RuntimeException('The package contains an unsafe file path and was rejected: ' . $name);
            }
        }

        if (!@mkdir($extractDir, 0750, true) && !is_dir($extractDir)) {
            $zip->close();
            throw new RuntimeException('Could not create the extraction folder.');
        }

        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            throw new RuntimeException('The package could not be extracted.');
        }
        $zip->close();

        // GitHub zipballs wrap everything in a single top-level folder.
        $entries = array_values(array_diff(scandir($extractDir) ?: [], ['.', '..']));
        $root = $extractDir;
        if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
            $root = $extractDir . '/' . $entries[0];
        }

        // Integrity check: the package must actually be this application.
        foreach (['index.php', 'bootstrap.php', 'app', 'config'] as $marker) {
            if (!file_exists($root . '/' . $marker)) {
                throw new RuntimeException('The package does not look like a Ganpati Quiz Show release (missing "' . $marker . '"). Nothing was changed.');
            }
        }

        return $root;
    }

    /**
     * Copy the new files over the application, skipping every protected path.
     * Returns the number of files written.
     */
    /**
     * Files that configure the web server rather than the application.
     * Plenty of hosts deliberately stop PHP writing these; that is a warning
     * worth showing, never a reason to roll back a working update.
     */
    private const ADVISORY_FILES = ['.htaccess', '.user.ini', 'php.ini', 'web.config', '.htpasswd'];

    /** @var array<int,string> Collected while files are applied. */
    private array $writeWarnings = [];

    /**
     * Files the host would not let PHP write during the last apply.
     *
     * @return array<int,string>
     */
    public function writeWarnings(): array
    {
        return $this->writeWarnings;
    }

    private function applyFiles(string $packageRoot, ?string $targetRoot = null): int
    {
        $appRoot = $targetRoot ?? Application::instance()->rootPath();
        $protected = $this->protectedPaths();
        $excluded = Config::get('updates.excluded_from_package', []);
        $excluded = is_array($excluded) ? $excluded : [];

        $count = 0;
        $this->writeWarnings = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($packageRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = ltrim(str_replace($packageRoot, '', $item->getPathname()), '/');
            if ($relative === '') {
                continue;
            }
            if ($this->isProtected($relative, $protected) || $this->isProtected($relative, $excluded)) {
                continue;
            }

            $target = $appRoot . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new RuntimeException('Could not create directory: ' . $relative);
                }
                continue;
            }

            $parent = dirname($target);
            if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
                throw new RuntimeException('Could not create directory: ' . dirname($relative));
            }
            if (!@copy($item->getPathname(), $target)) {
                if (in_array(basename($relative), self::ADVISORY_FILES, true)) {
                    // Shared hosting often locks these down. Say so and carry on.
                    $this->writeWarnings[] = $relative;
                    continue;
                }
                throw new RuntimeException(
                    'Could not write file: ' . $relative
                    . '. Check that the folder "' . (dirname($relative) === '.' ? 'application root' : dirname($relative))
                    . '" is writable by PHP.'
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * Snapshot everything the updater may overwrite, so a failure can be undone.
     */
    private function snapshotApplicationFiles(string $backupDir): void
    {
        $appRoot = Application::instance()->rootPath();
        if (!@mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Could not create the pre-update snapshot folder.');
        }

        $protected = $this->protectedPaths();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = ltrim(str_replace($appRoot, '', $item->getPathname()), '/');
            if ($relative === '' || $this->isProtected($relative, $protected) || str_starts_with($relative, '.git/')) {
                continue;
            }

            $target = $backupDir . '/' . $relative;
            if ($item->isDir()) {
                @mkdir($target, 0750, true);
            } elseif ($item->isFile()) {
                $parent = dirname($target);
                if (!is_dir($parent)) {
                    @mkdir($parent, 0750, true);
                }
                @copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Restore the files captured by snapshotApplicationFiles().
     *
     * Restores everything the snapshot holds and then removes any file the
     * failed update introduced, so a half-applied release (a broken new
     * migration, for example) cannot linger and be picked up later.
     */
    private function rollbackFiles(string $backupDir): int
    {
        $appRoot = Application::instance()->rootPath();
        $protected = $this->protectedPaths();
        $restored = 0;
        $snapshotted = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = ltrim(str_replace($backupDir, '', $item->getPathname()), '/');
            if ($relative === '' || $this->isProtected($relative, $protected)) {
                continue;
            }
            $snapshotted[$relative] = true;
            $target = $appRoot . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
            } elseif ($item->isFile()) {
                $parent = dirname($target);
                if (!is_dir($parent)) {
                    @mkdir($parent, 0755, true);
                }
                if (@copy($item->getPathname(), $target)) {
                    $restored++;
                }
            }
        }

        $this->removeFilesAddedByUpdate($appRoot, $snapshotted, $protected);

        return $restored;
    }

    /**
     * Delete files that exist now but were not in the pre-update snapshot.
     * Protected paths (.env, uploads, storage) are never considered, so user
     * data added during the update is safe.
     *
     * @param array<string,bool> $snapshotted
     * @param array<int,string>  $protected
     */
    private function removeFilesAddedByUpdate(string $appRoot, array $snapshotted, array $protected): void
    {
        if ($snapshotted === []) {
            // No usable snapshot - deleting anything would be guesswork.
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = ltrim(str_replace($appRoot, '', $item->getPathname()), '/');
            if ($relative === ''
                || isset($snapshotted[$relative])
                || $this->isProtected($relative, $protected)
                || str_starts_with($relative, '.git/')
            ) {
                continue;
            }

            if ($item->isFile()) {
                @unlink($item->getPathname());
            } elseif ($item->isDir() && (scandir($item->getPathname()) ?: []) === ['.', '..']) {
                @rmdir($item->getPathname());
            }
        }
    }

    /** @param array<int,string> $patterns */
    private function isProtected(string $relative, array $patterns): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        foreach ($patterns as $pattern) {
            $pattern = trim(str_replace('\\', '/', $pattern), '/');
            if ($pattern === '') {
                continue;
            }
            // Support trailing wildcards such as "public/uploads/*".
            $clean = rtrim($pattern, '/*');
            if ($relative === $clean || str_starts_with($relative, $clean . '/')) {
                return true;
            }
            if (fnmatch($pattern, $relative)) {
                return true;
            }
        }
        return false;
    }

    private function status(string $status): void
    {
        if ($this->updateId === null) {
            return;
        }
        $this->db->update('system_updates', [
            'status'     => $status,
            'log'        => implode("\n", $this->log),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $this->updateId]);
    }

    private function note(string $message): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $message;
        if ($this->updateId !== null) {
            $this->db->update('system_updates', [
                'log'        => implode("\n", $this->log),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $this->updateId]);
        }
    }

    /** @return array<string,mixed> */
    private function currentRecord(): array
    {
        if ($this->updateId === null) {
            return [];
        }
        $this->db->update('system_updates', ['log' => implode("\n", $this->log)], ['id' => $this->updateId]);
        return $this->find($this->updateId) ?? [];
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
}
