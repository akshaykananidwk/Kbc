<?php
declare(strict_types=1);

/**
 * Paths the GitHub updater must never touch, and the statuses an update
 * run moves through.
 */
return [
    'protected_paths' => [
        '.env',
        '.env.local',
        'config/local.php',
        'config.php',
        'public/uploads',
        'storage',
        '.htaccess.local',
    ],
    'excluded_from_package' => [
        '.git', '.github', '.gitignore', 'tests', 'node_modules', '.DS_Store',
    ],
    'statuses' => [
        'checking', 'downloading', 'backing_up', 'updating',
        'migrating', 'clearing_cache', 'completed', 'failed', 'rolled_back',
    ],
    'api_base' => 'https://api.github.com',
    'timeout'  => 120,
];
