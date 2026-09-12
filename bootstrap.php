<?php
declare(strict_types=1);

/**
 * Shared bootstrap for the web front controller and the CLI console.
 */

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(500);
    exit('Ganpati Bapa Quiz Show requires PHP 8.2 or newer. This server runs PHP ' . PHP_VERSION . '.');
}

require_once __DIR__ . '/app/Core/Autoloader.php';

$autoloader = new App\Core\Autoloader();
$autoloader->addNamespace('App', __DIR__ . '/app');
$autoloader->addNamespace('Database\\Seeders', __DIR__ . '/database/seeders');
$autoloader->addNamespace('Database', __DIR__ . '/database');
$autoloader->register();

require_once __DIR__ . '/app/Support/helpers.php';

$app = App\Core\Application::boot(__DIR__);

date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

return $app;
