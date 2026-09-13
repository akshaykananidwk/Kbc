<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Per-game question reuse.
 *
 * With reuse switched off globally, a question bank eventually runs dry and
 * the operator is left with a dead "Create the game" button minutes before a
 * show. This flag lets one game reuse questions without changing the setting
 * for every future show. NULL means "follow the global setting".
 */
return new class {
    public function up(Database $db): void
    {
        $exists = $db->selectOne("SHOW COLUMNS FROM games LIKE 'allow_repeat'");
        if ($exists !== null) {
            return;
        }
        $db->run('ALTER TABLE games ADD COLUMN allow_repeat TINYINT(1) NULL DEFAULT NULL AFTER question_order');
    }

    public function down(Database $db): void
    {
        $exists = $db->selectOne("SHOW COLUMNS FROM games LIKE 'allow_repeat'");
        if ($exists !== null) {
            $db->run('ALTER TABLE games DROP COLUMN allow_repeat');
        }
    }
};
