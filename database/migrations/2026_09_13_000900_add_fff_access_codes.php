<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Per-contender access codes for Fastest Finger First.
 *
 * Each contender is handed a short code so they answer on their own phone
 * as themselves. Without this anyone on the venue wifi could answer for
 * someone else.
 */
return new class {
    public function up(Database $db): void
    {
        if (!$db->columnExists('fff_entries', 'access_code')) {
            $db->pdo()->exec("ALTER TABLE `fff_entries` ADD COLUMN `access_code` CHAR(4) NULL AFTER `participant_id`");
            $db->pdo()->exec("CREATE UNIQUE INDEX `uq_fff_access` ON `fff_entries` (`round_id`, `access_code`)");
        }
    }

    public function down(Database $db): void
    {
        if ($db->columnExists('fff_entries', 'access_code')) {
            $db->pdo()->exec("DROP INDEX `uq_fff_access` ON `fff_entries`");
            $db->pdo()->exec("ALTER TABLE `fff_entries` DROP COLUMN `access_code`");
        }
    }
};
