<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\GiftRepository;
use App\Repositories\PrizeLevelRepository;
use App\Services\AuditService;
use App\Services\SettingsService;

final class PrizeLevelController extends Controller
{
    private PrizeLevelRepository $levels;

    public function __construct()
    {
        $this->levels = new PrizeLevelRepository();
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.prizes.index', [
            'ladder'     => $this->levels->ladder(false),
            'gifts'      => (new GiftRepository())->active(),
            'categories' => Database::instance()->select(
                "SELECT id, name FROM question_categories WHERE status = 'active' ORDER BY sort_order, name"
            ),
            'nextLevel'  => $this->levels->nextLevelNo(),
            'totalValue' => $this->levels->totalLadderValue(),
            'guaranteed' => $this->levels->guaranteedLevels(),
            'defaultTime'=> SettingsService::int('default_time_limit', 30),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validatePayload($request, null);
        $id = $this->levels->create($data);

        AuditService::log('prize.created', 'Added prize level ' . $data['level_no'] . ' worth ' . SettingsService::money($data['amount']), 'prize_level', $id);
        $this->success('Prize level added.');
        return $this->redirect('/admin/prizes');
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        if ($this->levels->find($id) === null) {
            $this->error('That prize level no longer exists.');
            return $this->redirect('/admin/prizes');
        }

        $data = $this->validatePayload($request, $id);
        $this->levels->updateById($id, $data);

        AuditService::log('prize.updated', 'Updated prize level ' . $data['level_no'], 'prize_level', $id);
        $this->success('Prize level updated.');
        return $this->redirect('/admin/prizes');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        $level = $this->levels->find($id);
        if ($level === null) {
            $this->error('That prize level no longer exists.');
            return $this->redirect('/admin/prizes');
        }

        $inUse = (int) (Database::instance()->scalar(
            'SELECT COUNT(*) FROM game_questions WHERE level_no = ?',
            [(int) $level['level_no']]
        ) ?? 0);

        if ($inUse > 0) {
            $this->levels->updateById($id, ['status' => 'inactive']);
            AuditService::log('prize.deactivated', 'Prize level ' . $level['level_no'] . ' is used by game history so was deactivated.', 'prize_level', $id);
            $this->success('This level has been played in ' . $inUse . ' game(s), so it was deactivated instead of deleted.');
            return $this->redirect('/admin/prizes');
        }

        $this->levels->deleteById($id);
        $this->levels->resequence();

        AuditService::log('prize.deleted', 'Deleted prize level ' . $level['level_no'], 'prize_level', $id);
        $this->success('Prize level deleted and the ladder renumbered.');
        return $this->redirect('/admin/prizes');
    }

    /** @return array<string,mixed> */
    private function validatePayload(Request $request, ?int $ignoreId): array
    {
        $data = Validator::validate($request->all(), [
            'level_no'    => 'required|int|between:1,999',
            'amount'      => 'required|numeric|min:0|max:999999999999',
            'label'       => 'nullable|string|max:80',
            'time_limit'  => 'required|int|between:5,600',
            'difficulty'  => 'required|in:any,easy,medium,hard,expert',
            'gift_id'     => 'nullable|int',
            'category_id' => 'nullable|int',
            'status'      => 'required|in:active,inactive',
        ], [
            'level_no'   => 'Question number',
            'amount'     => 'Prize amount',
            'time_limit' => 'Time limit',
        ]);

        $levelNo = (int) $data['level_no'];
        if ($this->levels->levelNoExists($levelNo, $ignoreId)) {
            throw new \App\Core\Exceptions\ValidationException(
                ['level_no' => 'Question number ' . $levelNo . ' already exists in the ladder.'],
                'That question number is already used in the ladder.'
            );
        }

        return [
            'level_no'      => $levelNo,
            'amount'        => (float) $data['amount'],
            'label'         => ($data['label'] ?? '') !== '' ? $data['label'] : 'Question ' . $levelNo,
            'is_guaranteed' => $request->bool('is_guaranteed', false) ? 1 : 0,
            'gift_id'       => ($data['gift_id'] ?? 0) > 0 ? (int) $data['gift_id'] : null,
            'category_id'   => ($data['category_id'] ?? 0) > 0 ? (int) $data['category_id'] : null,
            'time_limit'    => (int) $data['time_limit'],
            'difficulty'    => $data['difficulty'],
            'status'        => $data['status'],
        ];
    }
}
