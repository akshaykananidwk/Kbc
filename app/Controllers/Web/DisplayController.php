<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\GameService;
use App\Services\SettingsService;

/**
 * The audience / TV screen (monitor 2).
 *
 * This page renders no admin data at all - it boots with a sanitised
 * snapshot and then polls /api/display/state.
 */
final class DisplayController extends Controller
{
    public function index(Request $request): Response
    {
        $state = GameService::make()->displayState();

        return $this->view('display.screen', [
            'state'          => $state,
            'stateJson'      => json_encode($state, JSON_UNESCAPED_UNICODE),
            'pollInterval'   => max(200, SettingsService::int('poll_interval_ms', 700)),
            'fullscreenAuto' => SettingsService::bool('fullscreen_default', false),
            'fontScale'      => max(60, min(200, SettingsService::int('font_scale', 100))),
            'showLadder'     => SettingsService::bool('show_prize_ladder', true),
            'showLifelines'  => SettingsService::bool('show_lifelines', true),
            'animations'     => SettingsService::bool('animations_enabled', true),
            'soundEnabled'   => SettingsService::bool('sound_enabled', true),
            'timerStyle'     => SettingsService::string('timer_style', 'ring'),
            // Shown in the hover controls so anyone can see at a glance which
            // build the television is actually running.
            'appVersion'     => \App\Services\UpdateService::make()->currentVersion(),
            'ganpatiImage'   => \App\Core\Application::uploadUrl(SettingsService::string('ganpati_image', '')),
            'welcomeHeading' => SettingsService::string('welcome_heading', 'ગણપતિ બાપા મોરિયા'),
            'welcomeSub'     => SettingsService::string('welcome_subheading', ''),
        ]);
    }
}
