<?php
declare(strict_types=1);

use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `system_updates` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `old_version` VARCHAR(40) NULL,
            `new_version` VARCHAR(40) NULL,
            `commit_sha` VARCHAR(64) NULL,
            `commit_message` VARCHAR(500) NULL,
            `commit_date` DATETIME NULL,
            `commit_author` VARCHAR(150) NULL,
            `status` ENUM('checking','downloading','backing_up','updating','migrating','clearing_cache','completed','failed','rolled_back') NOT NULL DEFAULT 'checking',
            `backup_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
            `backup_path` VARCHAR(255) NULL,
            `migration_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
            `migrations_run` TEXT NULL,
            `files_changed` INT UNSIGNED NOT NULL DEFAULT 0,
            `rollback_status` VARCHAR(40) NOT NULL DEFAULT 'not_required',
            `error_message` TEXT NULL,
            `log` LONGTEXT NULL,
            `initiated_by` INT UNSIGNED NULL,
            `started_at` DATETIME NULL,
            `finished_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_updates_status` (`status`),
            CONSTRAINT `fk_updates_user` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `backups` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `filename` VARCHAR(255) NOT NULL,
            `path` VARCHAR(500) NOT NULL,
            `type` ENUM('database','files','full') NOT NULL DEFAULT 'database',
            `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `status` ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
            `note` VARCHAR(300) NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_backup_filename` (`filename`),
            KEY `idx_backups_type` (`type`),
            CONSTRAINT `fk_backups_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['backups', 'system_updates'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
};
