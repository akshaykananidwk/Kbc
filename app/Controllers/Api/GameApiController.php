<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GameRepository;
use App\Repositories\ParticipantRepository;
use App\Services\AuthService;
use App\Services\GameService;

/**
 * Operator facing game API. Every endpoint requires an authenticated
 * operator (or admin) and a valid CSRF token.
 */
final class GameApiController extends Controller
{
    private GameService $service;

    public function __construct()
    {
        $this->service = GameService::make();
    }

    /** Full operator state, including the correct answer. */
    public function state(Request $request): Response
    {
        $gameId = $request->int('game_id', 0);
        $state = $this->service->state($gameId > 0 ? $gameId : null, true);
        return $this->ok('Current game state.', $state);
    }

    public function create(Request $request): Response
    {
        $participantId = $request->int('participant_id', 0);
        if ($participantId < 1) {
            return $this->fail('Choose a participant first.', 422);
        }
        $order = $request->string('question_order', '');
        $state = $this->service->createGame(
            $participantId,
            AuthService::id(),
            $order === '' ? null : $order,
            $request->has('rehearsal') ? $request->bool('rehearsal', false) : null,
            $request->bool('replace_open_game', false),
            $request->has('allow_repeat') ? $request->bool('allow_repeat', false) : null
        );
        return $this->ok('Game created.', $state);
    }

    public function start(Request $request): Response
    {
        return $this->ok('Game started.', $this->service->startGame($this->gameId($request)));
    }

    public function startTimer(Request $request): Response
    {
        return $this->ok('Timer started.', $this->service->startTimer($this->gameId($request)));
    }

    public function pauseTimer(Request $request): Response
    {
        return $this->ok('Timer paused.', $this->service->pauseTimer($this->gameId($request)));
    }

    public function resumeTimer(Request $request): Response
    {
        return $this->ok('Timer resumed.', $this->service->resumeTimer($this->gameId($request)));
    }

    public function resetTimer(Request $request): Response
    {
        return $this->ok('Timer reset.', $this->service->resetTimer($this->gameId($request)));
    }

    public function timeUp(Request $request): Response
    {
        return $this->ok('Time up.', $this->service->markTimeUp($this->gameId($request)));
    }

    public function select(Request $request): Response
    {
        $option = $request->string('option', '');
        return $this->ok('Option ' . strtoupper($option) . ' selected.', $this->service->selectOption($this->gameId($request), $option));
    }

    public function lock(Request $request): Response
    {
        return $this->ok('Answer locked.', $this->service->lockAnswer($this->gameId($request)));
    }

    public function unlock(Request $request): Response
    {
        $reason = $request->string('reason', '');
        return $this->ok('Answer unlocked.', $this->service->unlockAnswer($this->gameId($request), $reason));
    }

    public function reveal(Request $request): Response
    {
        return $this->ok('Result revealed.', $this->service->revealResult($this->gameId($request)));
    }

    public function next(Request $request): Response
    {
        return $this->ok('Next question.', $this->service->nextQuestion($this->gameId($request)));
    }

    public function previous(Request $request): Response
    {
        return $this->ok('Moved to the previous question.', $this->service->previousQuestion($this->gameId($request)));
    }

    public function restart(Request $request): Response
    {
        return $this->ok('Question restarted.', $this->service->restartQuestion($this->gameId($request)));
    }

    /** Open live audience voting for the question on air. */
    public function openPoll(Request $request): Response
    {
        return $this->ok('Audience voting is open.', $this->service->openAudiencePoll($this->gameId($request)));
    }

    /** Close voting early, before the window elapses. */
    public function closePoll(Request $request): Response
    {
        return $this->ok('Audience voting closed.', $this->service->closeAudiencePoll($this->gameId($request)));
    }

    public function lifeline(Request $request): Response
    {
        $code = $request->string('code', '');
        return $this->ok('Lifeline used.', $this->service->useLifeline($this->gameId($request), $code));
    }

    public function quit(Request $request): Response
    {
        return $this->ok('Participant quit the game.', $this->service->quitGame($this->gameId($request)));
    }

    public function end(Request $request): Response
    {
        $status = $request->string('status', 'abandoned');
        return $this->ok('Game ended.', $this->service->endGame($this->gameId($request), $status));
    }

    public function reset(Request $request): Response
    {
        $startNew = $request->bool('start_new', false);
        return $this->ok('Game reset.', $this->service->resetGame($this->gameId($request), $startNew));
    }

    public function complete(Request $request): Response
    {
        return $this->ok('Game completed.', $this->service->completeGame($this->gameId($request)));
    }

    /** Operator-only preview of the upcoming question. */
    public function preview(Request $request): Response
    {
        $gameId = $this->gameId($request);
        $state = $this->service->state($gameId, true);
        if (($state['question'] ?? null) === null) {
            return $this->fail('No question is currently on air.', 404);
        }
        return $this->ok('Question preview.', [
            'level'          => $state['level'],
            'question'       => $state['question']['text'],
            'options'        => $state['private']['all_options'],
            'correct_option' => $state['private']['correct_option'],
            'explanation'    => $state['private']['explanation'],
            'prize'          => $state['prize']['current_label'],
            'gift'           => $state['prize']['gift_name'],
        ]);
    }

    public function participants(Request $request): Response
    {
        $repository = new ParticipantRepository();
        return $this->ok('Participants.', ['participants' => $repository->selectable()]);
    }

    public function summary(Request $request): Response
    {
        $gameId = $this->gameId($request);
        $games = new GameRepository();
        $game = $games->findDetailed($gameId);
        if ($game === null) {
            return $this->fail('Game not found.', 404);
        }
        return $this->ok('Game summary.', [
            'game'      => $game,
            'questions' => $games->questionsFor($gameId),
            'lifelines' => $games->lifelinesUsed($gameId),
            'gifts'     => $games->giftsWon($gameId),
        ]);
    }

    private function gameId(Request $request): int
    {
        $id = $request->int('game_id', 0);
        if ($id > 0) {
            return $id;
        }
        $active = (new GameRepository())->activeGame();
        if ($active === null) {
            throw new \App\Core\Exceptions\HttpException(404, 'There is no game in progress.');
        }
        return (int) $active['id'];
    }
}
