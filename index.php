<?php
declare(strict_types=1);

/**
 * Ganpati Bapa Quiz Show - front controller.
 *
 * Every request that is not a real file on disk is routed through here by
 * the .htaccess rewrite rules.
 */

$app = require __DIR__ . '/bootstrap.php';

$router = $app->router();
require __DIR__ . '/routes/api.php';
require __DIR__ . '/routes/web.php';

$app->run();
