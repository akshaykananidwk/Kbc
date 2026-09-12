<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Reporting indexes.
 *
 * The reports screen groups game_answers by level_no and orders questions by
 * times_used / times_correct. Both were full scans; these indexes make the
 * reports page stay fast as game history grows.
 */
return new class {
    public function up(Database $db): void
    {
        if (!$this->indexExists($db, 'game_answers', 'idx_ga_level')) {
            $db->pdo()->exec('CREATE INDEX `idx_ga_level` ON `game_answers` (`level_no`)');
        }
        if (!$this->indexExists($db, 'questions', 'idx_questions_usage')) {
            $db->pdo()->exec('CREATE INDEX `idx_questions_usage` ON `questions` (`times_used`, `times_correct`)');
        }
    }

    public function down(Database $db): void
    {
        if ($this->indexExists($db, 'game_answers', 'idx_ga_level')) {
            $db->pdo()->exec('DROP INDEX `idx_ga_level` ON `game_answers`');
        }
        if ($this->indexExists($db, 'questions', 'idx_questions_usage')) {
            $db->pdo()->exec('DROP INDEX `idx_questions_usage` ON `questions`');
        }
    }

    private function indexExists(Database $db, string $table, string $index): bool
    {
        $count = (int) ($db->scalar(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        ) ?? 0);
        return $count > 0;
    }
};
