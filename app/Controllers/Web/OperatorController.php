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
        ]);
    }

    /** Pre-show checklist: participant, ladder and question availability. */
    public function setup(Request $request): Response
    {
        $levels = new PrizeLevelRepository();
        $questions = new QuestionRepository();
        $games = new GameRepository();

        return $this->view('operator.setup', [
            'participants'  => (new ParticipantRepository())->selectable(),
            'ladder'        => $levels->ladder(),
            'activeGame'    => $games->activeGame(),
            'questionCount' => $questions->availableCount(SettingsService::bool('repeat_questions', false)),
            'totalActive'   => $questions->activeCount(),
            'allowReuse'    => SettingsService::bool('repeat_questions', false),
            'maxLevel'      => $levels->maxLevel(),
            'orderModes'    => [
                'fixed'      => 'Fixed order (by prize level, then sort order)',
                'random'     => 'Random questions',
                'category'   => 'By category set on each prize level',
                'difficulty' => 'By difficulty set on each prize level',
            ],
            'currentOrder'  => SettingsService::string('question_order', 'fixed'),
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
}
