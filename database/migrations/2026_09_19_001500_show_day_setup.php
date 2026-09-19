<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Everything a live show day needs:
 *
 *  - Two age groups (juniors 10-20, seniors 21-50) with questions that can be
 *    aimed at one group or left open to both.
 *  - A single switch that guarantees no question is asked twice on the day.
 *  - The three lifelines the show actually offers: 50:50, Phone a Friend and
 *    "પ્રશ્ન બદલી" - swap this question for another at the same prize.
 */
return new class {
    public function up(Database $db): void
    {
        $now = date('Y-m-d H:i:s');

        // --- Age groups ----------------------------------------------------
        if ($db->selectOne("SHOW COLUMNS FROM questions LIKE 'age_group'") === null) {
            $db->run("ALTER TABLE questions
                      ADD COLUMN age_group ENUM('any','junior','senior') NOT NULL DEFAULT 'any' AFTER difficulty");
            $db->run("CREATE INDEX idx_question_age_group ON questions (status, age_group)");
        }
        if ($db->selectOne("SHOW COLUMNS FROM participants LIKE 'age_group'") === null) {
            $db->run("ALTER TABLE participants
                      ADD COLUMN age_group ENUM('auto','junior','senior') NOT NULL DEFAULT 'auto' AFTER age");
        }

        // A question already switched away from must not come back in the
        // same game, even when the row that served it has been replaced.
        $db->run(
            'CREATE TABLE IF NOT EXISTS game_switched_questions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                game_id BIGINT UNSIGNED NOT NULL,
                question_id BIGINT UNSIGNED NOT NULL,
                level_no SMALLINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_switched (game_id, question_id),
                KEY idx_switched_game (game_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // --- Settings ------------------------------------------------------
        $settings = [
            ['game', 'no_repeat_today', '1', 'boolean', 'Never ask the same question twice today',
             'One switch for a show day: a question served at any point today is not served again, whatever the other settings say.', 46],
            ['game', 'junior_max_age', '20', 'integer', 'Juniors are up to this age',
             'Anyone of this age or younger is a junior; older participants are seniors.', 47],
            ['game', 'age_group_questions', '1', 'boolean', 'Match questions to the age group',
             'Juniors are asked junior and open questions (easier first), seniors senior and open ones.', 48],
        ];
        foreach ($settings as [$group, $key, $value, $type, $label, $description, $order]) {
            if ((int) ($db->scalar('SELECT COUNT(*) FROM settings WHERE key_name = ?', [$key]) ?? 0) > 0) {
                continue;
            }
            $db->insert('settings', [
                'group_name'  => $group,
                'key_name'    => $key,
                'value'       => $value,
                'type'        => $type,
                'label'       => $label,
                'description' => $description,
                'sort_order'  => $order,
                'is_secret'   => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // --- The three lifelines the show offers ---------------------------
        $switch = $db->selectOne("SELECT id, config FROM lifelines WHERE code = 'skip_question' LIMIT 1");
        if ($switch !== null) {
            $config = json_decode((string) $switch['config'], true);
            $config = is_array($config) ? $config : [];
            $config['keep_prize'] = true;

            $db->update('lifelines', [
                'name'        => 'પ્રશ્ન બદલી',
                'description' => 'Swaps this question for another one at the same prize level.',
                'icon'        => 'switch',
                'is_enabled'  => 1,
                'sort_order'  => 30,
                'config'      => json_encode($config, JSON_UNESCAPED_UNICODE),
                'updated_at'  => $now,
            ], ['id' => (int) $switch['id']]);
        }

        $db->run("UPDATE lifelines SET name = 'ફોન અ ફ્રેન્ડ', is_enabled = 1, sort_order = 20, updated_at = ?
                  WHERE code = 'phone_a_friend'", [$now]);
        $db->run("UPDATE lifelines SET is_enabled = 1, sort_order = 10, updated_at = ? WHERE code = 'fifty_fifty'", [$now]);

        // The flyer promises three lifelines, so the other two start switched
        // off. They stay configurable in Admin -> Lifelines.
        $db->run("UPDATE lifelines SET is_enabled = 0, updated_at = ?
                  WHERE code IN ('audience_poll','expert_advice')", [$now]);
    }

    public function down(Database $db): void
    {
        $db->run('DROP TABLE IF EXISTS game_switched_questions');
        if ($db->selectOne("SHOW COLUMNS FROM questions LIKE 'age_group'") !== null) {
            $db->run('ALTER TABLE questions DROP COLUMN age_group');
        }
        if ($db->selectOne("SHOW COLUMNS FROM participants LIKE 'age_group'") !== null) {
            $db->run('ALTER TABLE participants DROP COLUMN age_group');
        }
        $db->run("DELETE FROM settings WHERE key_name IN ('no_repeat_today','junior_max_age','age_group_questions')");
    }
};
