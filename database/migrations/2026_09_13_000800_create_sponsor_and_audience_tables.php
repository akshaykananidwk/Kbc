<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Sponsors shown on the idle display, plus the tables behind live audience
 * voting and the Fastest Finger First round.
 */
return new class {
    public function up(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `sponsors` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `tagline` VARCHAR(255) NULL,
            `logo_path` VARCHAR(255) NULL,
            `website` VARCHAR(255) NULL,
            `tier` ENUM('title','gold','silver','supporter') NOT NULL DEFAULT 'supporter',
            `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_sponsors_status` (`status`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // One row per audience voting session, opened by the operator.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `audience_polls` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `question_id` BIGINT UNSIGNED NOT NULL,
            `level_no` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `code` VARCHAR(12) NOT NULL,
            `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
            `opened_at` DATETIME NOT NULL,
            `closes_at` DATETIME NOT NULL,
            `closed_at` DATETIME NULL,
            `total_votes` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_poll_code` (`code`),
            KEY `idx_poll_game` (`game_id`),
            KEY `idx_poll_status` (`status`),
            CONSTRAINT `fk_poll_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_poll_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // One row per phone. voter_token is a random id held in the phone's
        // browser, so a device can change its mind but not stuff the ballot.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `audience_votes` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `poll_id` BIGINT UNSIGNED NOT NULL,
            `voter_token` CHAR(32) NOT NULL,
            `option_key` ENUM('A','B','C','D') NOT NULL,
            `ip_hash` CHAR(64) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_vote_per_device` (`poll_id`, `voter_token`),
            KEY `idx_vote_poll` (`poll_id`, `option_key`),
            CONSTRAINT `fk_vote_poll` FOREIGN KEY (`poll_id`) REFERENCES `audience_polls` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Fastest Finger First: a round, its contenders and their answers.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `fff_rounds` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `question_id` BIGINT UNSIGNED NULL,
            `question_text` TEXT NOT NULL,
            `correct_order` VARCHAR(15) NOT NULL,
            `time_limit` SMALLINT UNSIGNED NOT NULL DEFAULT 25,
            `status` ENUM('pending','running','closed') NOT NULL DEFAULT 'pending',
            `state_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
            `started_at` DECIMAL(16,3) NULL,
            `closed_at` DATETIME NULL,
            `winner_participant_id` BIGINT UNSIGNED NULL,
            `operator_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_fff_status` (`status`),
            CONSTRAINT `fk_fff_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_fff_winner` FOREIGN KEY (`winner_participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `fff_entries` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `round_id` BIGINT UNSIGNED NOT NULL,
            `participant_id` BIGINT UNSIGNED NOT NULL,
            `submitted_order` VARCHAR(15) NULL,
            `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
            `time_taken_ms` INT UNSIGNED NULL,
            `rank_position` SMALLINT UNSIGNED NULL,
            `submitted_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_fff_entry` (`round_id`, `participant_id`),
            KEY `idx_fff_entry_round` (`round_id`, `is_correct`, `time_taken_ms`),
            CONSTRAINT `fk_fff_entry_round` FOREIGN KEY (`round_id`) REFERENCES `fff_rounds` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_fff_entry_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Certificates issued, so a reprint gives the same serial number.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `certificates` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `game_id` BIGINT UNSIGNED NOT NULL,
            `participant_id` BIGINT UNSIGNED NULL,
            `serial_no` VARCHAR(40) NOT NULL,
            `participant_name` VARCHAR(150) NOT NULL,
            `prize_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `gifts` VARCHAR(500) NULL,
            `issued_by` INT UNSIGNED NULL,
            `issued_at` DATETIME NOT NULL,
            `print_count` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_certificate_serial` (`serial_no`),
            UNIQUE KEY `uq_certificate_game` (`game_id`),
            KEY `idx_certificate_participant` (`participant_id`),
            CONSTRAINT `fk_certificate_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_certificate_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_certificate_user` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Games can be marked as rehearsal so they never pollute reports.
        if (!$db->columnExists('games', 'is_rehearsal')) {
            $pdo->exec("ALTER TABLE `games` ADD COLUMN `is_rehearsal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `question_order`");
            $pdo->exec("CREATE INDEX `idx_games_rehearsal` ON `games` (`is_rehearsal`)");
        }
    }

    public function down(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['certificates', 'fff_entries', 'fff_rounds', 'audience_votes', 'audience_polls', 'sponsors'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        if ($db->columnExists('games', 'is_rehearsal')) {
            $pdo->exec('ALTER TABLE `games` DROP COLUMN `is_rehearsal`');
        }
    }
};
