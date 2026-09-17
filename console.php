<?php
declare(strict_types=1);

/**
 * Small CLI helper used for maintenance tasks:
 *
 *   php console.php migrate            Run pending migrations
 *   php console.php migrate:status     Show applied / pending migrations
 *   php console.php seed [--demo] [--questions]
 *                                      Run the seeders (--questions adds the
 *                                      175-question Gujarati bank)
 *   php console.php backup:database    Create a database backup
 *   php console.php cache:clear        Clear the application cache
 *   php console.php update:check       Check GitHub for a newer version
 */

if (PHP_SAPI !== 'cli') {
    exit('This script can only be run from the command line.');
}

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\BackupService;
use App\Services\CacheService;
use App\Services\MigrationService;
use App\Services\SeederService;
use App\Services\UpdateService;

$command = $argv[1] ?? 'help';
$flags = array_slice($argv, 2);

try {
    switch ($command) {
        case 'migrate':
            $ran = MigrationService::make()->migrate();
            echo $ran === []
                ? "Nothing to migrate. Database is up to date." . PHP_EOL
                : "Migrated:" . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $ran) . PHP_EOL;
            break;

        case 'migrate:status':
            $service = MigrationService::make();
            $applied = $service->applied();
            $pending = $service->pending();
            echo 'Applied (' . count($applied) . '):' . PHP_EOL;
            foreach ($applied as $name) {
                echo '  [x] ' . $name . PHP_EOL;
            }
            echo 'Pending (' . count($pending) . '):' . PHP_EOL;
            foreach ($pending as $name) {
                echo '  [ ] ' . $name . PHP_EOL;
            }
            break;

        case 'migrate:rollback':
            $rolled = MigrationService::make()->rollbackLastBatch();
            echo $rolled === []
                ? 'Nothing to roll back.' . PHP_EOL
                : 'Rolled back: ' . implode(', ', $rolled) . PHP_EOL;
            break;

        case 'seed':
            $seeder = SeederService::make();
            $seeder->seedCore();
            echo 'Core data seeded.' . PHP_EOL;
            if (in_array('--demo', $flags, true)) {
                $seeder->seedDemo();
                echo 'Demo data seeded.' . PHP_EOL;
            }
            if (in_array('--questions', $flags, true)) {
                $added = $seeder->seedQuestionBank();
                echo $added . ' question(s) added from the Gujarati question bank.' . PHP_EOL;
            }
            break;

        case 'backup:database':
            $backup = BackupService::make()->backupDatabase(null, 'CLI backup');
            echo 'Backup created: ' . $backup['filename'] . ' (' . $backup['size_human'] . ')' . PHP_EOL;
            break;

        case 'backup:files':
            $backup = BackupService::make()->backupFiles(null, 'CLI backup');
            echo 'Backup created: ' . $backup['filename'] . ' (' . $backup['size_human'] . ')' . PHP_EOL;
            break;

        case 'cache:clear':
            $count = CacheService::clearAll();
            echo 'Cache cleared (' . $count . ' entries removed).' . PHP_EOL;
            break;

        case 'update:check':
            $result = UpdateService::make()->checkForUpdate();
            echo 'Current version : ' . $result['current_version'] . PHP_EOL;
            echo 'Latest version  : ' . $result['latest_version'] . PHP_EOL;
            echo 'Update available: ' . ($result['update_available'] ? 'YES' : 'no') . PHP_EOL;
            if ($result['commit_message'] !== '') {
                echo 'Latest commit   : ' . $result['commit_message'] . PHP_EOL;
            }
            break;

        case 'db:check':
            $db = Database::instance();
            echo 'Connected to "' . $db->databaseName() . '" with ' . count($db->tables()) . ' tables.' . PHP_EOL;
            break;

        default:
            echo <<<TXT
            Ganpati Bapa Quiz Show - console

              migrate            Run pending migrations
              migrate:status     Show applied / pending migrations
              migrate:rollback   Roll back the last migration batch
              seed [--demo]      Seed core data (and optionally demo data)
              backup:database    Create a database backup
              backup:files       Create a files backup
              cache:clear        Clear the application cache
              update:check       Check GitHub for a newer version
              db:check           Verify the database connection

            TXT;
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
