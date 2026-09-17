<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Which categories take part in a balanced show.
 *
 * A balanced game deals questions out over the categories in rotation - with
 * five categories and a ten-level ladder that is two questions from each. Old
 * or one-off categories should not dilute that, so each category now says
 * whether it belongs in the rotation.
 */
return new class {
    public function up(Database $db): void
    {
        if ($db->selectOne("SHOW COLUMNS FROM question_categories LIKE 'in_rotation'") === null) {
            $db->run('ALTER TABLE question_categories ADD COLUMN in_rotation TINYINT(1) NOT NULL DEFAULT 1 AFTER status');
        }
    }

    public function down(Database $db): void
    {
        if ($db->selectOne("SHOW COLUMNS FROM question_categories LIKE 'in_rotation'") !== null) {
            $db->run('ALTER TABLE question_categories DROP COLUMN in_rotation');
        }
    }
};
