<?php
declare(strict_types=1);

use App\Core\Router;

/** @var Router $router */

// ---------------------------------------------------------------------
// Installation wizard (locks itself once finished)
// ---------------------------------------------------------------------
$router->group('/install', ['throttle:40,60', 'csrf'], function (Router $router): void {
    $router->get('/', 'App\Controllers\Install\InstallController@index');
    $router->get('/requirements', 'App\Controllers\Install\InstallController@requirements');
    $router->get('/database', 'App\Controllers\Install\InstallController@database');
    $router->post('/database', 'App\Controllers\Install\InstallController@saveDatabase');
    $router->get('/admin', 'App\Controllers\Install\InstallController@admin');
    $router->post('/admin', 'App\Controllers\Install\InstallController@saveAdmin');
    $router->get('/website', 'App\Controllers\Install\InstallController@website');
    $router->post('/website', 'App\Controllers\Install\InstallController@saveWebsite');
    $router->get('/run', 'App\Controllers\Install\InstallController@run');
    $router->post('/run', 'App\Controllers\Install\InstallController@runInstall');
    $router->get('/complete', 'App\Controllers\Install\InstallController@complete');
});

// ---------------------------------------------------------------------
// Public landing page
// ---------------------------------------------------------------------
$router->get('/', 'App\Controllers\Web\HomeController@index', ['installed']);
$router->get('/robots.txt', 'App\Controllers\Web\HomeController@robots');

// ---------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------
$router->get('/admin/login', 'App\Controllers\Admin\AuthController@showLogin', ['installed', 'guest', 'noindex']);
$router->post('/admin/login', 'App\Controllers\Admin\AuthController@login', ['installed', 'throttle:10,60', 'csrf', 'noindex']);
$router->post('/admin/logout', 'App\Controllers\Admin\AuthController@logout', ['installed', 'csrf', 'noindex']);
$router->get('/admin/forbidden', 'App\Controllers\Admin\AuthController@forbidden', ['installed', 'auth', 'noindex']);

// ---------------------------------------------------------------------
// Admin panel
// ---------------------------------------------------------------------
$router->group('/admin', ['installed', 'auth', 'noindex'], function (Router $router): void {
    $router->get('/', 'App\Controllers\Admin\DashboardController@index');
    $router->get('/profile', 'App\Controllers\Admin\ProfileController@edit');
    $router->post('/profile', 'App\Controllers\Admin\ProfileController@update', ['csrf']);
    $router->post('/profile/password', 'App\Controllers\Admin\ProfileController@changePassword', ['csrf']);

    // Questions
    $router->get('/questions', 'App\Controllers\Admin\QuestionController@index', ['admin']);
    $router->get('/questions/create', 'App\Controllers\Admin\QuestionController@create', ['admin']);
    $router->post('/questions', 'App\Controllers\Admin\QuestionController@store', ['admin', 'csrf']);
    $router->get('/questions/import', 'App\Controllers\Admin\QuestionController@importForm', ['admin']);
    $router->post('/questions/import', 'App\Controllers\Admin\QuestionController@import', ['admin', 'csrf']);
    $router->get('/questions/export', 'App\Controllers\Admin\QuestionController@export', ['admin']);
    $router->get('/questions/{id:\d+}', 'App\Controllers\Admin\QuestionController@show', ['admin']);
    $router->get('/questions/{id:\d+}/edit', 'App\Controllers\Admin\QuestionController@edit', ['admin']);
    $router->post('/questions/{id:\d+}', 'App\Controllers\Admin\QuestionController@update', ['admin', 'csrf']);
    $router->post('/questions/{id:\d+}/delete', 'App\Controllers\Admin\QuestionController@destroy', ['admin', 'csrf']);
    $router->post('/questions/{id:\d+}/duplicate', 'App\Controllers\Admin\QuestionController@duplicate', ['admin', 'csrf']);
    $router->post('/questions/{id:\d+}/toggle', 'App\Controllers\Admin\QuestionController@toggle', ['admin', 'csrf']);

    // Categories
    $router->get('/categories', 'App\Controllers\Admin\CategoryController@index', ['admin']);
    $router->post('/categories', 'App\Controllers\Admin\CategoryController@store', ['admin', 'csrf']);
    $router->post('/categories/{id:\d+}', 'App\Controllers\Admin\CategoryController@update', ['admin', 'csrf']);
    $router->post('/categories/{id:\d+}/delete', 'App\Controllers\Admin\CategoryController@destroy', ['admin', 'csrf']);

    // Participants
    $router->get('/participants', 'App\Controllers\Admin\ParticipantController@index');
    $router->get('/participants/create', 'App\Controllers\Admin\ParticipantController@create');
    $router->post('/participants', 'App\Controllers\Admin\ParticipantController@store', ['csrf']);
    $router->get('/participants/{id:\d+}/edit', 'App\Controllers\Admin\ParticipantController@edit');
    $router->post('/participants/{id:\d+}', 'App\Controllers\Admin\ParticipantController@update', ['csrf']);
    $router->post('/participants/{id:\d+}/delete', 'App\Controllers\Admin\ParticipantController@destroy', ['csrf']);

    // Prize ladder
    $router->get('/prizes', 'App\Controllers\Admin\PrizeLevelController@index', ['admin']);
    $router->post('/prizes', 'App\Controllers\Admin\PrizeLevelController@store', ['admin', 'csrf']);
    $router->post('/prizes/{id:\d+}', 'App\Controllers\Admin\PrizeLevelController@update', ['admin', 'csrf']);
    $router->post('/prizes/{id:\d+}/delete', 'App\Controllers\Admin\PrizeLevelController@destroy', ['admin', 'csrf']);

    // Gifts
    $router->get('/gifts', 'App\Controllers\Admin\GiftController@index', ['admin']);
    $router->get('/gifts/create', 'App\Controllers\Admin\GiftController@create', ['admin']);
    $router->post('/gifts', 'App\Controllers\Admin\GiftController@store', ['admin', 'csrf']);
    $router->get('/gifts/{id:\d+}/edit', 'App\Controllers\Admin\GiftController@edit', ['admin']);
    $router->post('/gifts/{id:\d+}', 'App\Controllers\Admin\GiftController@update', ['admin', 'csrf']);
    $router->post('/gifts/{id:\d+}/delete', 'App\Controllers\Admin\GiftController@destroy', ['admin', 'csrf']);

    // Lifelines
    $router->get('/lifelines', 'App\Controllers\Admin\LifelineController@index', ['admin']);
    $router->post('/lifelines/{id:\d+}', 'App\Controllers\Admin\LifelineController@update', ['admin', 'csrf']);

    // Games
    $router->get('/games', 'App\Controllers\Admin\GameHistoryController@index');
    $router->get('/games/{id:\d+}', 'App\Controllers\Admin\GameHistoryController@show');
    $router->get('/games/{id:\d+}/export', 'App\Controllers\Admin\GameHistoryController@export');
    $router->post('/games/{id:\d+}/delete', 'App\Controllers\Admin\GameHistoryController@destroy', ['admin', 'csrf']);

    // Sponsors
    $router->get('/sponsors', 'App\Controllers\Admin\SponsorController@index', ['admin']);
    $router->post('/sponsors', 'App\Controllers\Admin\SponsorController@store', ['admin', 'csrf']);
    $router->post('/sponsors/{id:\d+}', 'App\Controllers\Admin\SponsorController@update', ['admin', 'csrf']);
    $router->post('/sponsors/{id:\d+}/delete', 'App\Controllers\Admin\SponsorController@destroy', ['admin', 'csrf']);

    // Certificates
    $router->get('/certificates', 'App\Controllers\Admin\CertificateController@index');
    $router->get('/certificates/{id:\d+}', 'App\Controllers\Admin\CertificateController@show');

    // Reports
    $router->get('/reports', 'App\Controllers\Admin\ReportController@index');
    $router->get('/reports/games.csv', 'App\Controllers\Admin\ReportController@gamesCsv');
    $router->get('/reports/questions.csv', 'App\Controllers\Admin\ReportController@questionsCsv');
    $router->get('/reports/prizes.csv', 'App\Controllers\Admin\ReportController@prizesCsv');

    // Settings
    $router->get('/settings', 'App\Controllers\Admin\SettingsController@index', ['admin']);
    $router->post('/settings', 'App\Controllers\Admin\SettingsController@update', ['admin', 'csrf']);
    $router->post('/settings/upload', 'App\Controllers\Admin\SettingsController@upload', ['admin', 'csrf']);
    $router->post('/settings/remove-file', 'App\Controllers\Admin\SettingsController@removeFile', ['admin', 'csrf']);
    $router->post('/settings/demo/remove', 'App\Controllers\Admin\SettingsController@removeDemo', ['admin', 'csrf']);

    // Users
    $router->get('/users', 'App\Controllers\Admin\UserController@index', ['admin']);
    $router->get('/users/create', 'App\Controllers\Admin\UserController@create', ['admin']);
    $router->post('/users', 'App\Controllers\Admin\UserController@store', ['admin', 'csrf']);
    $router->get('/users/{id:\d+}/edit', 'App\Controllers\Admin\UserController@edit', ['admin']);
    $router->post('/users/{id:\d+}', 'App\Controllers\Admin\UserController@update', ['admin', 'csrf']);
    $router->post('/users/{id:\d+}/delete', 'App\Controllers\Admin\UserController@destroy', ['admin', 'csrf']);

    // System: backups, updates, logs
    $router->get('/backups', 'App\Controllers\Admin\BackupController@index', ['admin']);
    $router->post('/backups/database', 'App\Controllers\Admin\BackupController@createDatabase', ['admin', 'csrf']);
    $router->post('/backups/files', 'App\Controllers\Admin\BackupController@createFiles', ['admin', 'csrf']);
    $router->get('/backups/{id:\d+}/download', 'App\Controllers\Admin\BackupController@download', ['admin']);
    $router->post('/backups/{id:\d+}/restore', 'App\Controllers\Admin\BackupController@restore', ['admin', 'csrf']);
    $router->post('/backups/{id:\d+}/delete', 'App\Controllers\Admin\BackupController@destroy', ['admin', 'csrf']);

    $router->get('/updates', 'App\Controllers\Admin\UpdateController@index', ['admin']);
    $router->post('/updates/settings', 'App\Controllers\Admin\UpdateController@saveSettings', ['admin', 'csrf']);
    $router->get('/updates/{id:\d+}', 'App\Controllers\Admin\UpdateController@show', ['admin']);

    $router->get('/logs', 'App\Controllers\Admin\LogController@index', ['admin']);
    $router->get('/logs/audit', 'App\Controllers\Admin\LogController@audit', ['admin']);
    $router->get('/logs/audit.csv', 'App\Controllers\Admin\LogController@auditCsv', ['admin']);
});

// ---------------------------------------------------------------------
// Operator control screen (monitor 1)
// ---------------------------------------------------------------------
$router->group('/operator', ['installed', 'operator', 'noindex'], function (Router $router): void {
    $router->get('/', 'App\Controllers\Web\OperatorController@index');
    $router->get('/setup', 'App\Controllers\Web\OperatorController@setup');
    $router->get('/summary/{id:\d+}', 'App\Controllers\Web\OperatorController@summary');
});

// ---------------------------------------------------------------------
// Audience display screen (monitor 2) - deliberately public so the TV
// browser needs no sign-in, and deliberately read-only.
// ---------------------------------------------------------------------
$router->get('/display', 'App\Controllers\Web\DisplayController@index', ['installed', 'noindex']);
