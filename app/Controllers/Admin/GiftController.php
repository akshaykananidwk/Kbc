<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\GiftRepository;
use App\Services\AuditService;
use App\Support\Uploader;

final class GiftController extends Controller
{
    private GiftRepository $gifts;

    public function __construct()
    {
        $this->gifts = new GiftRepository();
    }

    public function index(Request $request): Response
    {
        $perPage = 20;
        $page = $this->page($request);
        $filters = ['search' => $request->string('search'), 'status' => $request->string('status')];
        $result = $this->gifts->paginate($filters, $page, $perPage);

        return $this->view('admin.gifts.index', [
            'gifts'      => $result['rows'],
            'pagination' => $this->pagination($result['total'], $page, $perPage, $filters),
            'filters'    => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin.gifts.form', ['gift' => null, 'levels' => $this->levels()]);
    }

    public function edit(Request $request): Response
    {
        $gift = $this->gifts->find($request->intParam('id'));
        if ($gift === null) {
            $this->error('That gift no longer exists.');
            return $this->redirect('/admin/gifts');
        }
        return $this->view('admin.gifts.form', ['gift' => $gift, 'levels' => $this->levels()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validatePayload($request);
        $image = $request->file('image');
        if ($image !== null) {
            $data['image_path'] = Uploader::store($image, 'gifts', ['image'], 4 * 1024 * 1024)['url'];
        }

        $id = $this->gifts->create($data);
        $this->attachLevel($request, $id);

        AuditService::log('gift.created', 'Added gift "' . $data['name'] . '"', 'gift', $id);
        $this->success('Gift saved.');
        return $this->redirect('/admin/gifts');
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        $existing = $this->gifts->find($id);
        if ($existing === null) {
            $this->error('That gift no longer exists.');
            return $this->redirect('/admin/gifts');
        }

        $data = $this->validatePayload($request);

        $image = $request->file('image');
        if ($image !== null) {
            $data['image_path'] = Uploader::store($image, 'gifts', ['image'], 4 * 1024 * 1024)['url'];
            Uploader::delete((string) ($existing['image_path'] ?? ''));
        } elseif ($request->bool('remove_image', false)) {
            Uploader::delete((string) ($existing['image_path'] ?? ''));
            $data['image_path'] = null;
        }

        $this->gifts->updateById($id, $data);
        $this->attachLevel($request, $id);

        AuditService::log('gift.updated', 'Updated gift #' . $id, 'gift', $id);
        $this->success('Gift updated.');
        return $this->redirect('/admin/gifts');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        $gift = $this->gifts->find($id);
        if ($gift === null) {
            $this->error('That gift no longer exists.');
            return $this->redirect('/admin/gifts');
        }

        $db = Database::instance();
        $awarded = (int) ($db->scalar('SELECT COUNT(*) FROM game_answers WHERE gift_id = ?', [$id]) ?? 0);
        if ($awarded > 0) {
            $this->gifts->updateById($id, ['status' => 'inactive']);
            AuditService::log('gift.deactivated', 'Gift #' . $id . ' was awarded ' . $awarded . ' time(s) so it was deactivated.', 'gift', $id);
            $this->success('This gift has already been awarded ' . $awarded . ' time(s), so it was deactivated instead of deleted.');
            return $this->redirect('/admin/gifts');
        }

        $db->run('UPDATE prize_levels SET gift_id = NULL WHERE gift_id = ?', [$id]);
        Uploader::delete((string) ($gift['image_path'] ?? ''));
        $this->gifts->deleteById($id);

        AuditService::log('gift.deleted', 'Deleted gift #' . $id, 'gift', $id);
        $this->success('Gift deleted.');
        return $this->redirect('/admin/gifts');
    }

    /** @return array<string,mixed> */
    private function validatePayload(Request $request): array
    {
        $data = Validator::validate($request->all(), [
            'name'           => 'required|string|max:150',
            'description'    => 'nullable|string|max:2000',
            'value_amount'   => 'nullable|numeric|min:0|max:99999999',
            'quantity_total' => 'required|int|between:0,100000',
            'serial_code'    => 'nullable|string|max:80',
            'status'         => 'required|in:active,inactive,out_of_stock',
            'sort_order'     => 'nullable|int|between:0,9999',
        ], [
            'value_amount'   => 'Gift value',
            'quantity_total' => 'Quantity',
        ]);

        return [
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'value_amount'   => (float) ($data['value_amount'] ?? 0),
            'quantity_total' => (int) $data['quantity_total'],
            'serial_code'    => $data['serial_code'] ?? null,
            'status'         => $data['status'],
            'sort_order'     => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** Optionally pin the gift to a prize level straight from the gift form. */
    private function attachLevel(Request $request, int $giftId): void
    {
        $levelId = $request->int('prize_level_id', 0);
        if ($levelId < 1) {
            return;
        }
        Database::instance()->run(
            'UPDATE prize_levels SET gift_id = ?, updated_at = ? WHERE id = ?',
            [$giftId, date('Y-m-d H:i:s'), $levelId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function levels(): array
    {
        return Database::instance()->select(
            'SELECT id, level_no, amount, gift_id FROM prize_levels ORDER BY level_no'
        );
    }
}
