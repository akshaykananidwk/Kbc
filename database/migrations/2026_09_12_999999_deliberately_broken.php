<?php
declare(strict_types=1);

use App\Core\Database;

/** Test fixture: fails on purpose so the updater's rollback can be verified. */
return new class {
    public function up(Database $db): void
    {
        $db->pdo()->exec('CREATE TABLE `rollback_probe` (id INT PRIMARY KEY) ENGINE=InnoDB');
        throw new RuntimeException('Deliberate failure to exercise the rollback path.');
    }

    public function down(Database $db): void
    {
        $db->pdo()->exec('DROP TABLE IF EXISTS `rollback_probe`');
    }
};
