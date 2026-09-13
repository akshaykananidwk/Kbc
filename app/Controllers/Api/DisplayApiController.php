<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\GameService;
use App\Services\SettingsService;

/**
 * Public display endpoint.
 *
 * SECURITY: this is the one API the audience screen talks to. It must never
 * expose the correct answer before the result is revealed, nor any admin,
 * credential or configuration data. The payload is built by
 * GameService::displayState() which whitelists every key it emits.
 */
final class DisplayApiController extends Controller
{
    /**
     * Light long-poll: waits briefly for the state version to change so the
     * TV updates almost instantly without hammering the server.
     */
    public function state(Request $request): Response
    {
        $service = GameService::make();
        $since = $request->int('v', -1);

        $state = $service->displayState();

        if ($since >= 0 && (int) $state['state_version'] === $since) {
            $deadline = microtime(true) + 2.5;
            while (microtime(true) < $deadline) {
                usleep(200000); // 200ms
                $state = $service->displayState();
                if ((int) $state['state_version'] !== $since) {
                    break;
                }
            }
        }

        $state['settings'] = $this->displaySettings();
        $state['idle'] = $this->idleContent($state);
        $state['fff'] = $this->fastestFinger();

        return Response::apiSuccess('Display state.', $state)
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Non-blocking variant for slow connections or debugging. */
    public function snapshot(Request $request): Response
    {
        $state = GameService::make()->displayState();
        $state['settings'] = $this->displaySettings();
        $state['idle'] = $this->idleContent($state);
        $state['fff'] = $this->fastestFinger();
        return Response::apiSuccess('Display snapshot.', $state);
    }

    /**
     * The Fastest Finger round for the audience screen.
     *
     * SECURITY: the service only includes the correct order once the round
     * is closed, and never marks an individual answer right or wrong before
     * then, so the display cannot leak the answer mid-round.
     *
     * @return array<string,mixed>|null
     */
    private function fastestFinger(): ?array
    {
        if (!SettingsService::bool('fff_enabled', true)) {
            return null;
        }
        return \App\Services\FastestFingerService::make()->currentState();
    }

    /**
     * Leaderboard and sponsors for the idle screen.
     *
     * Only built while no question is on air, so a live game never pays for
     * the extra queries.
     *
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function idleContent(array $state): array
    {
        // Idle covers "no game at all", "game created but not started" and
        // "game finished" - in the last case the display rotates between the
        // final result and the hall of fame rather than freezing on one screen.
        $isIdle = ($state['has_game'] ?? false) === false
            || ($state['question'] ?? null) === null
            || ($state['is_finished'] ?? false) === true;

        if (!$isIdle) {
            return ['active' => false, 'leaderboard' => [], 'summary' => null, 'sponsors' => []];
        }

        $leaderboard = [];
        $summary = null;
        if (SettingsService::bool('show_leaderboard', true)) {
            $service = \App\Services\LeaderboardService::make();
            $leaderboard = $service->top(SettingsService::int('leaderboard_count', 8));
            $summary = $service->summary();
        }

        $sponsors = [];
        if (SettingsService::bool('show_sponsors', true)) {
            foreach ((new \App\Repositories\SponsorRepository())->active() as $sponsor) {
                $sponsors[] = [
                    'name'    => (string) $sponsor['name'],
                    'tagline' => (string) ($sponsor['tagline'] ?? ''),
                    'tier'    => (string) $sponsor['tier'],
                    'logo'    => \App\Core\Application::uploadUrl($sponsor['logo_path'] ?? null),
                ];
            }
        }

        return [
            'active'      => true,
            'leaderboard' => $leaderboard,
            'summary'     => $summary,
            'sponsors'    => $sponsors,
        ];
    }

    /**
     * Only presentation settings are exposed - nothing from the security or
     * updates groups ever reaches the display screen.
     *
     * @return array<string,mixed>
     */
    private function displaySettings(): array
    {
        return [
            // The television is often left open for days. Telling it which
            // build the server is running lets it refresh itself after an
            // update instead of showing yesterday's screen all evening.
            'app_version'        => (string) \App\Core\Config::get('app.version', ''),
            'site_name'          => SettingsService::string('site_name', 'Ganpati Bapa Quiz Show'),
            'site_tagline'       => SettingsService::string('site_tagline', ''),
            'site_logo'          => \App\Core\Application::uploadUrl(SettingsService::string('site_logo', '')),
            'ganpati_image'      => \App\Core\Application::uploadUrl(SettingsService::string('ganpati_image', '')),
            'welcome_heading'    => SettingsService::string('welcome_heading', ''),
            'welcome_subheading' => SettingsService::string('welcome_subheading', ''),
            'footer_text'        => SettingsService::string('footer_text', ''),
            'animations'         => SettingsService::bool('animations_enabled', true),
            'sound'              => SettingsService::bool('sound_enabled', true),
            'timer_style'        => SettingsService::string('timer_style', 'ring'),
            'font_scale'         => SettingsService::int('font_scale', 100),
            'show_prize_ladder'  => SettingsService::bool('show_prize_ladder', true),
            'show_lifelines'     => SettingsService::bool('show_lifelines', true),
            'poll_interval_ms'   => max(200, SettingsService::int('poll_interval_ms', 700)),
            'currency'           => SettingsService::currency(),
            'primary_color'      => SettingsService::string('primary_color', '#b3141a'),
            'secondary_color'    => SettingsService::string('secondary_color', '#f5a623'),
            'accent_color'       => SettingsService::string('accent_color', '#ffd76e'),
            'music_enabled'      => SettingsService::bool('music_enabled', true),
            'music_volume'       => SettingsService::int('music_background_volume', 25),
            'sound_volume'       => SettingsService::int('sound_volume', 80),
            'synth_fallback'     => SettingsService::bool('sound_synth_fallback', true),
            'duck_on_effect'     => SettingsService::bool('music_duck_on_question', true),
            'intro_loop'         => SettingsService::bool('music_intro_loop', true),
            'show_leaderboard'   => SettingsService::bool('show_leaderboard', true),
            'show_sponsors'      => SettingsService::bool('show_sponsors', true),
            'cheque_animation'   => SettingsService::bool('cheque_animation', true),
            'idle_rotate_ms'     => max(3000, SettingsService::int('leaderboard_rotate_ms', 9000)),
            'music'              => [
                'intro'      => \App\Core\Application::uploadUrl(SettingsService::string('music_intro', '')),
                'background' => \App\Core\Application::uploadUrl(SettingsService::string('music_background', '')),
                'suspense'   => \App\Core\Application::uploadUrl(SettingsService::string('music_suspense', '')),
                'victory'    => \App\Core\Application::uploadUrl(SettingsService::string('music_victory', '')),
            ],
            'sounds'             => [
                'question_start' => \App\Core\Application::uploadUrl(SettingsService::string('sound_question_start', '')),
                'timer_start'    => \App\Core\Application::uploadUrl(SettingsService::string('sound_timer_start', '')),
                'answer_lock'    => \App\Core\Application::uploadUrl(SettingsService::string('sound_answer_lock', '')),
                'correct_answer' => \App\Core\Application::uploadUrl(SettingsService::string('sound_correct_answer', '')),
                'wrong_answer'   => \App\Core\Application::uploadUrl(SettingsService::string('sound_wrong_answer', '')),
                'prize_won'      => \App\Core\Application::uploadUrl(SettingsService::string('sound_prize_won', '')),
                'lifeline_used'  => \App\Core\Application::uploadUrl(SettingsService::string('sound_lifeline_used', '')),
                'final_win'      => \App\Core\Application::uploadUrl(SettingsService::string('sound_final_win', '')),
                'game_over'      => \App\Core\Application::uploadUrl(SettingsService::string('sound_game_over', '')),
            ],
        ];
    }
}
