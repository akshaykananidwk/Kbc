<?php
declare(strict_types=1);

use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `media` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `disk_path` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(120) NOT NULL,
            `extension` VARCHAR(12) NOT NULL,
            `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `type` ENUM('image','audio','video','other') NOT NULL DEFAULT 'image',
            `collection` VARCHAR(60) NOT NULL DEFAULT 'general',
            `uploaded_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_media_collection` (`collection`),
            KEY `idx_media_type` (`type`),
            CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `question_categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(120) NOT NULL,
            `slug` VARCHAR(140) NOT NULL,
            `description` VARCHAR(300) NULL,
            `colour` VARCHAR(20) NOT NULL DEFAULT '#d97706',
            `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_categories_slug` (`slug`),
            KEY `idx_categories_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `questions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_id` INT UNSIGNED NULL,
            `question_text` TEXT NOT NULL,
            `correct_option` ENUM('A','B','C','D') NOT NULL,
            `explanation` TEXT NULL,
            `difficulty` ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'medium',
            `time_limit` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            `prize_level` SMALLINT UNSIGNED NULL,
            `lifelines_allowed` TINYINT(1) NOT NULL DEFAULT 1,
            `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
            `image_path` VARCHAR(255) NULL,
            `audio_path` VARCHAR(255) NULL,
            `video_path` VARCHAR(255) NULL,
            `times_used` INT UNSIGNED NOT NULL DEFAULT 0,
            `times_correct` INT UNSIGNED NOT NULL DEFAULT 0,
            `times_wrong` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_questions_category` (`category_id`),
            KEY `idx_questions_status` (`status`),
            KEY `idx_questions_difficulty` (`difficulty`),
            KEY `idx_questions_level` (`prize_level`),
            KEY `idx_questions_sort` (`sort_order`),
            CONSTRAINT `fk_questions_category` FOREIGN KEY (`category_id`) REFERENCES `question_categories` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_questions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `question_options` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `question_id` BIGINT UNSIGNED NOT NULL,
            `option_key` ENUM('A','B','C','D') NOT NULL,
            `option_text` TEXT NOT NULL,
            `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_option_per_question` (`question_id`, `option_key`),
            CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `participants` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `registration_no` VARCHAR(40) NULL,
            `name` VARCHAR(150) NOT NULL,
            `mobile` VARCHAR(20) NULL,
            `email` VARCHAR(190) NULL,
            `city` VARCHAR(120) NULL,
            `age` TINYINT UNSIGNED NULL,
            `gender` ENUM('male','female','other','unspecified') NOT NULL DEFAULT 'unspecified',
            `photo_path` VARCHAR(255) NULL,
            `notes` TEXT NULL,
            `status` ENUM('active','inactive','played') NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_participants_reg` (`registration_no`),
            KEY `idx_participants_status` (`status`),
            KEY `idx_participants_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['participants', 'question_options', 'questions', 'question_categories', 'media'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
};
