<?php
declare(strict_types=1);

use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `level` TINYINT UNSIGNED NOT NULL DEFAULT 10,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_roles_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `permissions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(80) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `group_name` VARCHAR(60) NOT NULL DEFAULT 'general',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_permissions_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `role_permissions` (
            `role_id` INT UNSIGNED NOT NULL,
            `permission_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`role_id`, `permission_id`),
            KEY `idx_rp_permission` (`permission_id`),
            CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `role_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `username` VARCHAR(60) NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(30) NULL,
            `avatar` VARCHAR(255) NULL,
            `status` ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
            `last_login_at` DATETIME NULL,
            `last_login_ip` VARCHAR(45) NULL,
            `failed_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `locked_until` DATETIME NULL,
            `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_users_email` (`email`),
            UNIQUE KEY `uq_users_username` (`username`),
            KEY `idx_users_role` (`role_id`),
            KEY `idx_users_status` (`status`),
            CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `identifier` VARCHAR(190) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `successful` TINYINT(1) NOT NULL DEFAULT 0,
            `user_agent` VARCHAR(255) NULL,
            `attempted_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_attempts_identifier` (`identifier`, `attempted_at`),
            KEY `idx_attempts_ip` (`ip_address`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `user_name` VARCHAR(120) NULL,
            `action` VARCHAR(80) NOT NULL,
            `entity_type` VARCHAR(60) NULL,
            `entity_id` BIGINT UNSIGNED NULL,
            `description` VARCHAR(500) NULL,
            `meta` TEXT NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_audit_user` (`user_id`),
            KEY `idx_audit_action` (`action`, `created_at`),
            KEY `idx_audit_entity` (`entity_type`, `entity_id`),
            CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `group_name` VARCHAR(50) NOT NULL DEFAULT 'general',
            `key_name` VARCHAR(100) NOT NULL,
            `value` LONGTEXT NULL,
            `type` ENUM('string','integer','boolean','float','json','text') NOT NULL DEFAULT 'string',
            `is_secret` TINYINT(1) NOT NULL DEFAULT 0,
            `label` VARCHAR(150) NULL,
            `description` VARCHAR(300) NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_settings_key` (`key_name`),
            KEY `idx_settings_group` (`group_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['settings', 'audit_logs', 'login_attempts', 'users', 'role_permissions', 'permissions', 'roles'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
};
