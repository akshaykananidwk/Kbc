<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditService;
use App\Support\Str;

final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Database::instance()->select(
            'SELECT c.*, (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id) AS question_count
             FROM question_categories c ORDER BY c.sort_order, c.name'
        );
        return $this->view('admin.categories.index', ['categories' => $categories]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validatePayload($request);
        $data['slug'] = $this->uniqueSlug($data['name'], null);

        $id = Database::instance()->insert('question_categories', array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        AuditService::log('category.created', 'Added category "' . $data['name'] . '"', 'category', $id);
        $this->success('Category added.');
        return $this->redirect('/admin/categories');
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        $db = Database::instance();
        $existing = $db->selectOne('SELECT * FROM question_categories WHERE id = ?', [$id]);
        if ($existing === null) {
            $this->error('That category no longer exists.');
            return $this->redirect('/admin/categories');
        }

        $data = $this->validatePayload($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $id);
        $data['updated_at'] = date('Y-m-d H:i:s');

        $db->update('question_categories', $data, ['id' => $id]);
        AuditService::log('category.updated', 'Updated category #' . $id, 'category', $id);
        $this->success('Category updated.');
        return $this->redirect('/admin/categories');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        $db = Database::instance();
        $count = (int) ($db->scalar('SELECT COUNT(*) FROM questions WHERE category_id = ?', [$id]) ?? 0);

        // Questions keep working - the foreign key sets category_id to NULL.
        $db->delete('question_categories', ['id' => $id]);

        AuditService::log('category.deleted', 'Deleted category #' . $id . ' (' . $count . ' question(s) became uncategorised)', 'category', $id);
        $this->success($count > 0
            ? 'Category deleted. ' . $count . ' question(s) are now uncategorised.'
            : 'Category deleted.');
        return $this->redirect('/admin/categories');
    }

    /** @return array<string,mixed> */
    private function validatePayload(Request $request): array
    {
        $data = Validator::validate($request->all(), [
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:300',
            'colour'      => 'nullable|regex:^#[0-9a-fA-F]{6}$',
            'status'      => 'required|in:active,inactive',
            'sort_order'  => 'nullable|int|between:0,9999',
        ], ['colour' => 'Colour']);

        return [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'colour'      => $data['colour'] ?? '#d97706',
            'status'      => $data['status'],
            // Whether a balanced game deals questions from this category.
            'in_rotation' => $request->bool('in_rotation', false) ? 1 : 0,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;
        $db = Database::instance();

        while (true) {
            $sql = 'SELECT COUNT(*) FROM question_categories WHERE slug = ?';
            $bindings = [$slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> ?';
                $bindings[] = $ignoreId;
            }
            if ((int) ($db->scalar($sql, $bindings) ?? 0) === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$suffix);
        }
    }
}
