<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name'             => Env::get('APP_NAME', 'Ganpati Bapa Quiz Show'),
    'version'          => trim((string) @file_get_contents(dirname(__DIR__) . '/VERSION')) ?: '1.0.0',
    'debug'            => (bool) Env::get('APP_DEBUG', false),
    'url'              => Env::get('APP_URL', ''),
    'timezone'         => Env::get('APP_TIMEZONE', 'Asia/Kolkata'),
    'locale'           => Env::get('APP_LOCALE', 'gu'),
    'key'              => Env::get('APP_KEY', ''),
    'session_name'     => 'GANPATI_QUIZ_SESSION',
    'session_lifetime' => (int) (Env::get('SESSION_LIFETIME', 7200) ?? 7200),
];
