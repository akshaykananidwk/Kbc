<?php
declare(strict_types=1);

use App\Core\Router;

/** @var Router $router */

// ---------------------------------------------------------------------
// /api/display - the only endpoint the audience screen may call.
// Read-only, unauthenticated (the TV browser has no session) and
// sanitised: it never contains the correct answer before the reveal.
// ---------------------------------------------------------------------
$router->group('/api/display', ['installed', 'noindex'], function (Router $router): void {
    $router->get('/state', 'App\Controllers\Api\DisplayApiController@state');
    $router->get('/snapshot', 'App\Controllers\Api\DisplayApiController@snapshot');
});

// ---------------------------------------------------------------------
// /api/auth
// ---------------------------------------------------------------------
$router->group('/api/auth', ['installed', 'noindex'], function (Router $router): void {
    $router->get('/me', 'App\Controllers\Api\AuthApiController@me');
    $router->post('/logout', 'App\Controllers\Api\AuthApiController@logout', ['auth', 'csrf']);
});

// ---------------------------------------------------------------------
// /api/game + /api/operator + /api/lifelines - operator control surface
// ---------------------------------------------------------------------
$router->group('/api/game', ['installed', 'operator', 'noindex'], function (Router $router): void {
    $router->get('/state', 'App\Controllers\Api\GameApiController@state');
    $router->get('/preview', 'App\Controllers\Api\GameApiController@preview');
    $router->get('/summary', 'App\Controllers\Api\GameApiController@summary');
    $router->get('/participants', 'App\Controllers\Api\GameApiController@participants');

    $router->post('/create', 'App\Controllers\Api\GameApiController@create', ['csrf']);
    $router->post('/start', 'App\Controllers\Api\GameApiController@start', ['csrf']);
    $router->post('/timer/start', 'App\Controllers\Api\GameApiController@startTimer', ['csrf']);
    $router->post('/timer/pause', 'App\Controllers\Api\GameApiController@pauseTimer', ['csrf']);
    $router->post('/timer/resume', 'App\Controllers\Api\GameApiController@resumeTimer', ['csrf']);
    $router->post('/timer/reset', 'App\Controllers\Api\GameApiController@resetTimer', ['csrf']);
    $router->post('/timer/expire', 'App\Controllers\Api\GameApiController@timeUp', ['csrf']);
    $router->post('/select', 'App\Controllers\Api\GameApiController@select', ['csrf']);
    $router->post('/lock', 'App\Controllers\Api\GameApiController@lock', ['csrf']);
    $router->post('/unlock', 'App\Controllers\Api\GameApiController@unlock', ['csrf']);
    $router->post('/reveal', 'App\Controllers\Api\GameApiController@reveal', ['csrf']);
    $router->post('/next', 'App\Controllers\Api\GameApiController@next', ['csrf']);
    $router->post('/previous', 'App\Controllers\Api\GameApiController@previous', ['csrf']);
    $router->post('/restart', 'App\Controllers\Api\GameApiController@restart', ['csrf']);
    $router->post('/quit', 'App\Controllers\Api\GameApiController@quit', ['csrf']);
    $router->post('/end', 'App\Controllers\Api\GameApiController@end', ['csrf']);
    $router->post('/reset', 'App\Controllers\Api\GameApiController@reset', ['csrf']);
    $router->post('/complete', 'App\Controllers\Api\GameApiController@complete', ['csrf']);
});

$router->group('/api/operator', ['installed', 'operator', 'noindex'], function (Router $router): void {
    $router->get('/state', 'App\Controllers\Api\GameApiController@state');
    $router->get('/participants', 'App\Controllers\Api\GameApiController@participants');
});

$router->group('/api/lifelines', ['installed', 'operator', 'noindex'], function (Router $router): void {
    $router->get('/', 'App\Controllers\Api\LifelineApiController@index');
    $router->post('/use', 'App\Controllers\Api\GameApiController@lifeline', ['csrf']);
});

// ---------------------------------------------------------------------
// /api/questions - admin only
// ---------------------------------------------------------------------
$router->group('/api/questions', ['installed', 'admin', 'noindex'], function (Router $router): void {
    $router->get('/', 'App\Controllers\Api\QuestionApiController@index');
    $router->get('/{id:\d+}', 'App\Controllers\Api\QuestionApiController@show');
});

// ---------------------------------------------------------------------
// /api/settings - admin only
// ---------------------------------------------------------------------
$router->group('/api/settings', ['installed', 'admin', 'noindex'], function (Router $router): void {
    $router->get('/', 'App\Controllers\Api\SettingsApiController@index');
    $router->post('/', 'App\Controllers\Api\SettingsApiController@update', ['csrf']);
});

// ---------------------------------------------------------------------
// /api/updates - GitHub update manager, admin only
// ---------------------------------------------------------------------
$router->group('/api/updates', ['installed', 'admin', 'noindex'], function (Router $router): void {
    $router->get('/check', 'App\Controllers\Api\UpdateApiController@check');
    $router->get('/history', 'App\Controllers\Api\UpdateApiController@history');
    $router->post('/run', 'App\Controllers\Api\UpdateApiController@run', ['csrf']);
    $router->post('/rollback', 'App\Controllers\Api\UpdateApiController@rollback', ['csrf']);
    $router->post('/cache/clear', 'App\Controllers\Api\UpdateApiController@clearCache', ['csrf']);
});
