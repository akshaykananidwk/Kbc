<?php
declare(strict_types=1);

use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `games` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_code` VARCHAR(30) NOT NULL,
            `participant_id` BIGINT UNSIGNED NULL,
            `operator_id` INT UNSIGNED NULL,
            `status` ENUM('pending','running','paused','completed','wrong_answer','time_up','quit','abandoned') NOT NULL DEFAULT 'pending',
            `state` VARCHAR(40) NOT NULL DEFAULT 'GAME_NOT_STARTED',
            `state_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
            `current_level` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `current_question_id` BIGINT UNSIGNED NULL,
            `selected_option` ENUM('A','B','C','D') NULL,
            `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
            `timer_running` TINYINT(1) NOT NULL DEFAULT 0,
            `timer_started_at` DECIMAL(16,3) NULL,
            `timer_remaining_ms` INT NOT NULL DEFAULT 0,
            `timer_total_ms` INT NOT NULL DEFAULT 0,
            `questions_attempted` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `questions_correct` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `current_winnings` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `guaranteed_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `final_prize` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `question_order` VARCHAR(20) NOT NULL DEFAULT 'fixed',
            `started_at` DATETIME NULL,
            `ended_at` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_games_code` (`game_code`),
            KEY `idx_games_participant` (`participant_id`),
            KEY `idx_games_operator` (`operator_id`),
            KEY `idx_games_status` (`status`),
            KEY `idx_games_created` (`created_at`),
            CONSTRAINT `fk_games_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_games_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_games_question` FOREIGN KEY (`current_question_id`) REFERENCES `questions` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `game_questions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `question_id` BIGINT UNSIGNED NOT NULL,
            `level_no` SMALLINT UNSIGNED NOT NULL,
            `prize_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `gift_id` INT UNSIGNED NULL,
            `time_limit` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            `served_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_game_level` (`game_id`, `level_no`),
            KEY `idx_gq_question` (`question_id`),
            CONSTRAINT `fk_gq_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_gq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_gq_gift` FOREIGN KEY (`gift_id`) REFERENCES `gifts` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `game_answers` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `game_question_id` BIGINT UNSIGNED NOT NULL,
            `question_id` BIGINT UNSIGNED NOT NULL,
            `level_no` SMALLINT UNSIGNED NOT NULL,
            `selected_option` ENUM('A','B','C','D') NULL,
            `correct_option` ENUM('A','B','C','D') NOT NULL,
            `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
            `result` ENUM('correct','wrong','timeout','skipped','quit') NOT NULL DEFAULT 'wrong',
            `time_taken_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `prize_awarded` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `gift_id` INT UNSIGNED NULL,
            `was_overridden` TINYINT(1) NOT NULL DEFAULT 0,
            `answered_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_answer_per_game_question` (`game_question_id`),
            KEY `idx_ga_game` (`game_id`),
            KEY `idx_ga_question` (`question_id`),
            KEY `idx_ga_result` (`result`),
            CONSTRAINT `fk_ga_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ga_gq` FOREIGN KEY (`game_question_id`) REFERENCES `game_questions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ga_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ga_gift` FOREIGN KEY (`gift_id`) REFERENCES `gifts` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `game_lifelines` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `lifeline_id` INT UNSIGNED NOT NULL,
            `lifeline_code` VARCHAR(40) NOT NULL,
            `question_id` BIGINT UNSIGNED NULL,
            `level_no` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `payload` TEXT NULL,
            `used_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_gl_game` (`game_id`),
            KEY `idx_gl_code` (`lifeline_code`),
            CONSTRAINT `fk_gl_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_gl_lifeline` FOREIGN KEY (`lifeline_id`) REFERENCES `lifelines` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `game_events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `event_type` VARCHAR(60) NOT NULL,
            `state` VARCHAR(40) NULL,
            `level_no` SMALLINT UNSIGNED NULL,
            `payload` TEXT NULL,
            `user_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ge_game` (`game_id`, `id`),
            KEY `idx_ge_type` (`event_type`),
            CONSTRAINT `fk_ge_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ge_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['game_events', 'game_lifelines', 'game_answers', 'game_questions', 'games'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
};
