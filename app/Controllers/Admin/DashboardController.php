<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Application;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GameRepository;
use App\Repositories\GiftRepository;
use App\Repositories\PrizeLevelRepository;
use App\Repositories\QuestionRepository;
use App\Services\SettingsService;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $db = Database::instance();
        $games = new GameRepository();
        $questions = new QuestionRepository();
        $gifts = new GiftRepository();
        $levels = new PrizeLevelRepository();

        $stats = $games->statistics();

        return $this->view('admin.dashboard', [
            'cards' => [
                'total_questions'   => $questions->count(),
                'active_questions'  => $questions->activeCount(),
                'total_games'       => (int) ($stats['total_games'] ?? 0),
                'completed_games'   => (int) ($stats['completed_games'] ?? 0),
                'total_participants'=> (int) ($db->scalar('SELECT COUNT(*) FROM participants') ?? 0),
                'total_prize'       => (float) ($stats['total_prize'] ?? 0),
                'total_gifts'       => (int) ($db->scalar('SELECT COUNT(*) FROM gifts') ?? 0),
                'gifts_awarded'     => (int) ($db->scalar('SELECT COUNT(*) FROM game_answers WHERE gift_id IS NOT NULL') ?? 0),
                'ladder_levels'     => $levels->maxLevel(),
                'ladder_top'        => $levels->amountForLevel($levels->maxLevel()),
                'gift_value'        => $gifts->totalValueAwarded(),
            ],
            'recentGames' => $games->recent(8),
            'activeGame'  => $games->activeGame(),
            'system'      => $this->systemStatus(),
            'readiness'   => $this->readiness($questions, $levels, $db),
        ]);
    }

    /** @return array<string,mixed> */
    private function systemStatus(): array
    {
        $app = Application::instance();
        return [
            'php_version'    => PHP_VERSION,
            'database'       => Database::instance()->databaseName(),
            'app_version'    => (string) \App\Core\Config::get('app.version', '1.0.0'),
            'debug_mode'     => $app->isDebug(),
            'timezone'       => date_default_timezone_get(),
            'storage_writable' => is_writable($app->storagePath()),
            'uploads_writable' => is_writable(Application::uploadPath()),
            'install_locked' => is_file($app->storagePath('installed.lock')),
            'github_configured' => SettingsService::hasValue('github_owner') && SettingsService::hasValue('github_repo'),
            'last_backup'    => Database::instance()->scalar(
                "SELECT created_at FROM backups WHERE status = 'completed' ORDER BY id DESC LIMIT 1"
            ),
        ];
    }

    /**
     * Simple pre-show checklist so a non-technical operator can see at a
     * glance whether the system is ready to run a live game.
     *
     * @return array<int,array{label:string,ok:bool,hint:string}>
     */
    private function readiness(QuestionRepository $questions, PrizeLevelRepository $levels, Database $db): array
    {
        $allowReuse = SettingsService::bool('repeat_questions', false);
        $activeQuestions = $questions->activeCount();
        $availableQuestions = $questions->availableCount($allowReuse);
        $maxLevel = $levels->maxLevel();
        $participants = (int) ($db->scalar("SELECT COUNT(*) FROM participants WHERE status IN ('active','played')") ?? 0);
        $lifelines = (int) ($db->scalar('SELECT COUNT(*) FROM lifelines WHERE is_enabled = 1') ?? 0);

        return [
            [
                'label' => 'Prize ladder configured',
                'ok'    => $maxLevel > 0,
                'hint'  => $maxLevel > 0 ? $maxLevel . ' levels' : 'Add prize levels',
            ],
            [
                'label' => $allowReuse ? 'Enough active questions' : 'Enough unused questions for the next game',
                'ok'    => $availableQuestions >= max(1, $maxLevel),
                'hint'  => $allowReuse
                    ? $activeQuestions . ' active / ' . $maxLevel . ' needed'
                    : $availableQuestions . ' unused of ' . $activeQuestions . ' active / ' . $maxLevel . ' needed',
            ],
            [
                'label' => 'At least one participant',
                'ok'    => $participants > 0,
                'hint'  => $participants . ' registered',
            ],
            [
                'label' => 'Lifelines enabled',
                'ok'    => $lifelines > 0,
                'hint'  => $lifelines . ' enabled',
            ],
        ];
    }
}
