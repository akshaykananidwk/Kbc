<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GameRepository;
use App\Repositories\GiftRepository;
use App\Repositories\LifelineRepository;
use App\Repositories\ParticipantRepository;
use App\Repositories\QuestionRepository;
use App\Services\SettingsService;
use App\Support\Csv;

final class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $games = new GameRepository();
        $questions = new QuestionRepository();

        return $this->view('admin.reports.index', [
            'gameStats'    => $games->statistics(),
            'hardest'      => $questions->hardest(10),
            'mostUsed'     => $questions->mostUsed(10),
            'giftStats'    => (new GiftRepository())->distribution(),
            'winnings'     => (new ParticipantRepository())->winnings(15),
            'lifelineUse'  => (new LifelineRepository())->usageStats(),
            'byStatus'     => Database::instance()->select(
                'SELECT status, COUNT(*) AS total, COALESCE(SUM(final_prize), 0) AS prize
                 FROM games GROUP BY status ORDER BY total DESC'
            ),
            'byLevel'      => Database::instance()->select(
                'SELECT level_no,
                        COUNT(*) AS attempts,
                        SUM(is_correct) AS correct,
                        ROUND(AVG(time_taken_ms) / 1000, 1) AS avg_seconds
                 FROM game_answers GROUP BY level_no ORDER BY level_no'
            ),
        ]);
    }

    public function gamesCsv(Request $request): Response
    {
        $rows = Database::instance()->select(
            'SELECT g.game_code, p.name AS participant, p.city, u.name AS operator, g.status,
                    g.questions_attempted, g.questions_correct, g.final_prize,
                    g.started_at, g.ended_at,
                    TIMESTAMPDIFF(SECOND, g.started_at, g.ended_at) AS duration_seconds,
                    (SELECT COUNT(*) FROM game_lifelines gl WHERE gl.game_id = g.id) AS lifelines_used,
                    (SELECT COUNT(*) FROM game_answers ga WHERE ga.game_id = g.id AND ga.gift_id IS NOT NULL) AS gifts_won
             FROM games g
             LEFT JOIN participants p ON p.id = g.participant_id
             LEFT JOIN users u ON u.id = g.operator_id
             ORDER BY g.id DESC'
        );

        return $this->csv('games-report', [
            'Game code', 'Participant', 'City', 'Operator', 'Status', 'Questions attempted',
            'Questions correct', 'Final prize', 'Started', 'Ended', 'Duration (s)', 'Lifelines used', 'Gifts won',
        ], array_map(static fn ($r) => [
            $r['game_code'], $r['participant'] ?? '', $r['city'] ?? '', $r['operator'] ?? '', $r['status'],
            $r['questions_attempted'], $r['questions_correct'],
            SettingsService::money((float) $r['final_prize']),
            $r['started_at'] ?? '', $r['ended_at'] ?? '', $r['duration_seconds'] ?? '',
            $r['lifelines_used'], $r['gifts_won'],
        ], $rows));
    }

    public function questionsCsv(Request $request): Response
    {
        $rows = Database::instance()->select(
            'SELECT q.id, q.question_text, c.name AS category, q.difficulty, q.times_used,
                    q.times_correct, q.times_wrong,
                    ROUND(CASE WHEN q.times_used > 0 THEN q.times_correct / q.times_used * 100 ELSE 0 END, 1) AS success_rate
             FROM questions q LEFT JOIN question_categories c ON c.id = q.category_id
             ORDER BY q.times_used DESC, q.id'
        );

        return $this->csv('questions-report', [
            'ID', 'Question', 'Category', 'Difficulty', 'Times used', 'Correct', 'Wrong', 'Success rate %',
        ], array_map(static fn ($r) => [
            $r['id'], $r['question_text'], $r['category'] ?? '', $r['difficulty'],
            $r['times_used'], $r['times_correct'], $r['times_wrong'], $r['success_rate'],
        ], $rows));
    }

    public function prizesCsv(Request $request): Response
    {
        $rows = Database::instance()->select(
            'SELECT p.name AS participant, p.city, COUNT(g.id) AS games,
                    COALESCE(SUM(g.final_prize), 0) AS total_won,
                    COALESCE(MAX(g.final_prize), 0) AS best,
                    (SELECT COUNT(*) FROM game_answers ga
                       INNER JOIN games g2 ON g2.id = ga.game_id
                       WHERE g2.participant_id = p.id AND ga.gift_id IS NOT NULL) AS gifts
             FROM participants p INNER JOIN games g ON g.participant_id = p.id
             GROUP BY p.id, p.name, p.city ORDER BY total_won DESC'
        );

        return $this->csv('prize-report', [
            'Participant', 'City', 'Games played', 'Total won', 'Best prize', 'Gifts won',
        ], array_map(static fn ($r) => [
            $r['participant'], $r['city'] ?? '', $r['games'],
            SettingsService::money((float) $r['total_won']),
            SettingsService::money((float) $r['best']),
            $r['gifts'],
        ], $rows));
    }

    /**
     * @param array<int,string> $header
     * @param array<int,array<int,mixed>> $rows
     */
    private function csv(string $name, array $header, array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        Csv::put($handle, $header);
        foreach ($rows as $row) {
            Csv::put($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return Response::make($csv)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '-' . date('Y-m-d') . '.csv"');
    }
}
