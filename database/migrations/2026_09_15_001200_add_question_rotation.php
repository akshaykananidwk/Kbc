<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Question rotation.
 *
 * With a bank of 150-200 questions and reuse allowed, picking at random meant
 * the same questions kept coming back while others were never seen. These two
 * columns record when a question was last served and how often, so the picker
 * can work through the whole bank before repeating anything.
 */
return new class {
    public function up(Database $db): void
    {
        if ($db->selectOne("SHOW COLUMNS FROM questions LIKE 'last_served_at'") === null) {
            $db->run('ALTER TABLE questions ADD COLUMN last_served_at DATETIME NULL DEFAULT NULL AFTER times_wrong');
        }
        if ($db->selectOne("SHOW COLUMNS FROM questions LIKE 'times_served'") === null) {
            $db->run('ALTER TABLE questions ADD COLUMN times_served INT UNSIGNED NOT NULL DEFAULT 0 AFTER times_wrong');
        }
        if ($db->selectOne("SHOW INDEX FROM questions WHERE Key_name = 'idx_question_rotation'") === null) {
            $db->run('CREATE INDEX idx_question_rotation ON questions (status, times_served, last_served_at)');
        }

        // Seed from the games already played, so rotation starts from reality.
        $db->run(
            'UPDATE questions q
             SET q.times_served = (SELECT COUNT(*) FROM game_questions gq WHERE gq.question_id = q.id),
                 q.last_served_at = (SELECT MAX(gq.served_at) FROM game_questions gq WHERE gq.question_id = q.id)'
        );

        if ((int) ($db->scalar("SELECT COUNT(*) FROM settings WHERE key_name = 'question_rotation'") ?? 0) === 0) {
            $now = date('Y-m-d H:i:s');
            $db->insert('settings', [
                'group_name'  => 'game',
                'key_name'    => 'question_rotation',
                'value'       => '1',
                'type'        => 'boolean',
                'label'       => 'Rotate through the question bank',
                'description' => 'When questions may be reused, serve the least recently used ones first so the whole bank is used before anything repeats.',
                'sort_order'  => 45,
                'is_secret'   => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(Database $db): void
    {
        if ($db->selectOne("SHOW INDEX FROM questions WHERE Key_name = 'idx_question_rotation'") !== null) {
            $db->run('DROP INDEX idx_question_rotation ON questions');
        }
        if ($db->selectOne("SHOW COLUMNS FROM questions LIKE 'last_served_at'") !== null) {
            $db->run('ALTER TABLE questions DROP COLUMN last_served_at');
        }
        if ($db->selectOne("SHOW COLUMNS FROM questions LIKE 'times_served'") !== null) {
            $db->run('ALTER TABLE questions DROP COLUMN times_served');
        }
        $db->run("DELETE FROM settings WHERE key_name = 'question_rotation'");
    }
};
