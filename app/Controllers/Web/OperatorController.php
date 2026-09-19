<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GameRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\PrizeLevelRepository;
use App\Repositories\QuestionRepository;
use App\Services\GameService;
use App\Services\SettingsService;

final class OperatorController extends Controller
{
    /** The live control screen (monitor 1). */
    public function index(Request $request): Response
    {
        $service = GameService::make();
        $state = $service->state(null, true);

        return $this->view('operator.console', [
            'state'          => $state,
            'stateJson'      => json_encode($state, JSON_UNESCAPED_UNICODE),
            'participants'   => (new ParticipantRepository())->selectable(),
            'pollInterval'   => max(300, SettingsService::int('poll_interval_ms', 700)),
            'allowQuit'      => SettingsService::bool('allow_quit', true),
            'requireLock'    => SettingsService::bool('require_lock_before_reveal', true),
            'displayUrl'     => \App\Core\Application::url('/display'),
            'audio'          => [
                'soundEnabled'  => SettingsService::bool('sound_enabled', true),
                'musicEnabled'  => false, // music belongs on the TV, not the control desk
                'synthFallback' => SettingsService::bool('sound_synth_fallback', true),
                'soundVolume'   => SettingsService::int('sound_volume', 80),
            ],
        ]);
    }

    /** Pre-show checklist: participant, ladder and question availability. */
    public function setup(Request $request): Response
    {
        $levels = new PrizeLevelRepository();
        $questions = new QuestionRepository();
        $games = new GameRepository();

        return $this->view('operator.setup', [
            'participants'  => $this->withAgeGroups((new ParticipantRepository())->selectable()),
            'ladder'        => $levels->ladder(),
            'activeGame'    => $games->activeGame(),
            'questionCount' => $questions->availableCount(SettingsService::bool('repeat_questions', false)),
            'totalActive'   => $questions->activeCount(),
            'allowReuse'    => SettingsService::bool('repeat_questions', false),
            'bank'          => $questions->bankStatus(),
            'noRepeatToday' => SettingsService::bool('no_repeat_today', true),
            'todayLeft'     => [
                'junior' => $questions->availableToday('junior'),
                'senior' => $questions->availableToday('senior'),
                'any'    => $questions->availableToday(''),
            ],
            'juniorMaxAge'  => SettingsService::int('junior_max_age', 20),
            'rotation'      => SettingsService::bool('question_rotation', true),
            'maxLevel'      => $levels->maxLevel(),
            'orderModes'    => [
                'balanced'   => 'Two questions from every category (balanced)',
                'fixed'      => 'Fixed order (by prize level, then sort order)',
                'random'     => 'Random questions',
                'category'   => 'By category set on each prize level',
                'difficulty' => 'By difficulty set on each prize level',
            ],
            'currentOrder'  => SettingsService::string('question_order', 'fixed'),
            'rehearsalDefault' => SettingsService::bool('rehearsal_mode', false),
        ]);
    }

    /** Fastest Finger First control screen. */
    public function fastestFinger(Request $request): Response
    {
        return $this->view('operator.fff', [
            'participants' => (new ParticipantRepository())->selectable(),
            'enabled'      => SettingsService::bool('fff_enabled', true),
            'defaultTime'  => SettingsService::int('fff_time_limit', 25),
            'history'      => \App\Services\FastestFingerService::make()->history(15),
        ]);
    }

    /** Post-game report shown to the operator. */
    public function summary(Request $request): Response
    {
        $gameId = $request->intParam('id');
        $games = new GameRepository();
        $game = $games->findDetailed($gameId);

        if ($game === null) {
            $this->error('That game could not be found.');
            return $this->redirect('/operator');
        }

        return $this->view('operator.summary', [
            'game'      => $game,
            'questions' => $games->questionsFor($gameId),
            'lifelines' => $games->lifelinesUsed($gameId),
            'gifts'     => $games->giftsWon($gameId),
        ]);
    }
    /**
     * The show-day switch: one button that guarantees no question is asked
     * twice today. Deliberately a single control - during a live show there
     * is no time to hunt through settings.
     */
    public function noRepeatToday(Request $request): Response
    {
        $enabled = $request->bool('enabled', true);
        SettingsService::set('no_repeat_today', $enabled);

        \App\Services\AuditService::log(
            'settings.no_repeat_today',
            $enabled ? 'Turned on "no repeats today".' : 'Turned off "no repeats today".',
            'settings'
        );

        $this->success($enabled
            ? 'આજે કોઈ પ્રશ્ન ફરી નહીં પુછાય.'
            : 'આજે પ્રશ્ન ફરી પુછાઈ શકે છે.');

        return $this->redirect('/operator/setup');
    }

    /**
     * Tags each participant with the group they play in, so the operator can
     * see at a glance whether the next contestant is a junior or a senior.
     *
     * @param array<int,array<string,mixed>> $participants
     * @return array<int,array<string,mixed>>
     */
    private function withAgeGroups(array $participants): array
    {
        $engine = GameService::make();
        foreach ($participants as $index => $participant) {
            $participants[$index]['age_group'] = $engine->ageGroupFor((int) $participant['id']);
        }
        return $participants;
    }

}
