<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GameRepository;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Support\Csv;

final class GameHistoryController extends Controller
{
    private GameRepository $games;

    public function __construct()
    {
        $this->games = new GameRepository();
    }

    public function index(Request $request): Response
    {
        $perPage = 20;
        $page = $this->page($request);
        $filters = [
            'search' => $request->string('search'),
            'status' => $request->string('status'),
            'from'   => $request->string('from'),
            'to'     => $request->string('to'),
            'rehearsal' => $request->string('rehearsal'),
        ];

        $result = $this->games->paginate($filters, $page, $perPage);

        return $this->view('admin.games.index', [
            'games'      => $result['rows'],
            'pagination' => $this->pagination($result['total'], $page, $perPage, $filters),
            'filters'    => $filters,
            'statuses'   => \App\Core\Config::get('game.game_statuses', []),
            'stats'      => $this->games->statistics(),
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $request->intParam('id');
        $game = $this->games->findDetailed($id);
        if ($game === null) {
            $this->error('That game could not be found.');
            return $this->redirect('/admin/games');
        }

        return $this->view('admin.games.show', [
            'game'      => $game,
            'questions' => $this->games->questionsFor($id),
            'lifelines' => $this->games->lifelinesUsed($id),
            'gifts'     => $this->games->giftsWon($id),
            'events'    => $this->games->events($id, 200),
        ]);
    }

    public function export(Request $request): Response
    {
        $id = $request->intParam('id');
        $game = $this->games->findDetailed($id);
        if ($game === null) {
            $this->error('That game could not be found.');
            return $this->redirect('/admin/games');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        Csv::put($handle, ['Game', $game['game_code']]);
        Csv::put($handle, ['Participant', $game['participant_name'] ?? '']);
        Csv::put($handle, ['Operator', $game['operator_name'] ?? '']);
        Csv::put($handle, ['Status', $game['status']]);
        Csv::put($handle, ['Final prize', SettingsService::money((float) $game['final_prize'])]);
        Csv::put($handle, ['Started', $game['started_at'] ?? '']);
        Csv::put($handle, ['Ended', $game['ended_at'] ?? '']);
        Csv::put($handle, []);
        Csv::put($handle, ['Level', 'Question', 'Selected', 'Correct', 'Result', 'Time (s)', 'Prize', 'Gift']);

        foreach ($this->games->questionsFor($id) as $row) {
            Csv::put($handle, [
                $row['level_no'],
                $row['question_text'],
                $row['selected_option'] ?? '-',
                $row['correct_option'],
                $row['result'] ?? 'not answered',
                round(((int) ($row['time_taken_ms'] ?? 0)) / 1000, 1),
                SettingsService::money((float) ($row['prize_awarded'] ?? 0)),
                $row['gift_name'] ?? '',
            ]);
        }

        Csv::put($handle, []);
        Csv::put($handle, ['Lifelines used']);
        foreach ($this->games->lifelinesUsed($id) as $row) {
            Csv::put($handle, [$row['lifeline_name'], 'Level ' . $row['level_no'], $row['used_at']]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return Response::make($csv)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="game-' . $game['game_code'] . '.csv"');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        $game = $this->games->findDetailed($id);
        if ($game === null) {
            $this->error('That game could not be found.');
            return $this->redirect('/admin/games');
        }
        if (in_array((string) $game['status'], ['running', 'paused', 'pending'], true)) {
            $this->error('This game is still in progress. End it from the operator screen first.');
            return $this->redirect('/admin/games');
        }

        $this->games->deleteById($id);
        AuditService::log('game.deleted', 'Deleted game ' . $game['game_code'], 'game', $id);
        $this->success('Game record deleted.');
        return $this->redirect('/admin/games');
    }
}
