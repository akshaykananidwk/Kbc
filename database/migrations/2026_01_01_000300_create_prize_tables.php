<?php
declare(strict_types=1);

use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `gifts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `description` TEXT NULL,
            `image_path` VARCHAR(255) NULL,
            `value_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `quantity_total` INT UNSIGNED NOT NULL DEFAULT 1,
            `quantity_used` INT UNSIGNED NOT NULL DEFAULT 0,
            `serial_code` VARCHAR(80) NULL,
            `status` ENUM('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_gifts_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `prize_levels` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `level_no` SMALLINT UNSIGNED NOT NULL,
            `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `label` VARCHAR(80) NULL,
            `is_guaranteed` TINYINT(1) NOT NULL DEFAULT 0,
            `gift_id` INT UNSIGNED NULL,
            `time_limit` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            `difficulty` ENUM('any','easy','medium','hard','expert') NOT NULL DEFAULT 'any',
            `category_id` INT UNSIGNED NULL,
            `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_prize_level_no` (`level_no`),
            KEY `idx_prize_gift` (`gift_id`),
            KEY `idx_prize_guaranteed` (`is_guaranteed`),
            CONSTRAINT `fk_prize_gift` FOREIGN KEY (`gift_id`) REFERENCES `gifts` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_prize_category` FOREIGN KEY (`category_id`) REFERENCES `question_categories` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `lifelines` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(40) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `description` VARCHAR(300) NULL,
            `icon` VARCHAR(40) NOT NULL DEFAULT '',
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `uses_per_game` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `config` TEXT NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_lifeline_code` (`code`),
            KEY `idx_lifeline_enabled` (`is_enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['lifelines', 'prize_levels', 'gifts'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
};
