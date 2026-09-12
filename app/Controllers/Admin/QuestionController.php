<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\PrizeLevelRepository;
use App\Repositories\QuestionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\SettingsService;
use App\Support\Uploader;
use App\Support\Csv;

final class QuestionController extends Controller
{
    private QuestionRepository $questions;

    public function __construct()
    {
        $this->questions = new QuestionRepository();
    }

    public function index(Request $request): Response
    {
        $perPage = 20;
        $page = $this->page($request);
        $filters = [
            'search'      => $request->string('search'),
            'category_id' => $request->int('category_id'),
            'difficulty'  => $request->string('difficulty'),
            'status'      => $request->string('status'),
            'prize_level' => $request->int('prize_level'),
        ];

        $result = $this->questions->paginate($filters, $page, $perPage);

        return $this->view('admin.questions.index', [
            'questions'  => $result['rows'],
            'pagination' => $this->pagination($result['total'], $page, $perPage, $filters),
            'filters'    => $filters,
            'categories' => $this->categories(),
            'maxLevel'   => (new PrizeLevelRepository())->maxLevel(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin.questions.form', [
            'question'   => null,
            'options'    => ['A' => '', 'B' => '', 'C' => '', 'D' => ''],
            'categories' => $this->categories(),
            'maxLevel'   => (new PrizeLevelRepository())->maxLevel(),
            'defaultTime'=> SettingsService::int('default_time_limit', 30),
        ]);
    }

    public function edit(Request $request): Response
    {
        $question = $this->questions->findWithOptions($request->intParam('id'));
        if ($question === null) {
            $this->error('That question no longer exists.');
            return $this->redirect('/admin/questions');
        }

        return $this->view('admin.questions.form', [
            'question'   => $question,
            'options'    => array_merge(['A' => '', 'B' => '', 'C' => '', 'D' => ''], $question['options']),
            'categories' => $this->categories(),
            'maxLevel'   => (new PrizeLevelRepository())->maxLevel(),
            'defaultTime'=> SettingsService::int('default_time_limit', 30),
        ]);
    }

    public function show(Request $request): Response
    {
        $question = $this->questions->findWithOptions($request->intParam('id'));
        if ($question === null) {
            $this->error('That question no longer exists.');
            return $this->redirect('/admin/questions');
        }
        return $this->view('admin.questions.preview', ['question' => $question]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validatePayload($request);
        $options = $this->optionPayload($request);

        $media = $this->handleMedia($request, null);
        $data = array_merge($data, $media);
        $data['created_by'] = AuthService::id();

        $id = $this->questions->createWithOptions($data, $options);

        AuditService::log('question.created', 'Added question #' . $id, 'question', $id);
        $this->success('Question saved.');
        return $this->redirect('/admin/questions');
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        $existing = $this->questions->find($id);
        if ($existing === null) {
            $this->error('That question no longer exists.');
            return $this->redirect('/admin/questions');
        }

        $data = $this->validatePayload($request);
        $options = $this->optionPayload($request);
        $data = array_merge($data, $this->handleMedia($request, $existing));

        foreach (['image', 'audio', 'video'] as $kind) {
            if ($request->bool('remove_' . $kind, false)) {
                Uploader::delete((string) ($existing[$kind . '_path'] ?? ''));
                $data[$kind . '_path'] = null;
            }
        }

        $this->questions->updateWithOptions($id, $data, $options);

        AuditService::log('question.updated', 'Updated question #' . $id, 'question', $id);
        $this->success('Question updated.');
        return $this->redirect('/admin/questions');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        $question = $this->questions->find($id);
        if ($question === null) {
            $this->error('That question no longer exists.');
            return $this->redirect('/admin/questions');
        }

        $usedInGames = (int) (Database::instance()->scalar(
            'SELECT COUNT(*) FROM game_questions WHERE question_id = ?',
            [$id]
        ) ?? 0);

        if ($usedInGames > 0) {
            // Preserve game history: deactivate instead of deleting.
            $this->questions->updateById($id, ['status' => 'inactive']);
            AuditService::log('question.deactivated', 'Question #' . $id . ' was used in ' . $usedInGames . ' game(s) so it was deactivated instead of deleted.', 'question', $id);
            $this->success('This question has been used in a game, so it was deactivated instead of deleted (game history is preserved).');
            return $this->redirect('/admin/questions');
        }

        foreach (['image_path', 'audio_path', 'video_path'] as $column) {
            Uploader::delete((string) ($question[$column] ?? ''));
        }
        $this->questions->deleteById($id);

        AuditService::log('question.deleted', 'Deleted question #' . $id, 'question', $id);
        $this->success('Question deleted.');
        return $this->redirect('/admin/questions');
    }

    public function duplicate(Request $request): Response
    {
        $id = $request->intParam('id');
        $newId = $this->questions->duplicate($id, AuthService::id());
        if ($newId === null) {
            $this->error('That question no longer exists.');
            return $this->redirect('/admin/questions');
        }
        AuditService::log('question.duplicated', 'Duplicated question #' . $id . ' into #' . $newId, 'question', $newId);
        $this->success('Question duplicated. The copy is inactive until you review it.');
        return $this->redirect('/admin/questions/' . $newId . '/edit');
    }

    public function toggle(Request $request): Response
    {
        $id = $request->intParam('id');
        $question = $this->questions->find($id);
        if ($question === null) {
            $this->error('That question no longer exists.');
            return $this->redirect('/admin/questions');
        }
        $status = (string) $question['status'] === 'active' ? 'inactive' : 'active';
        $this->questions->updateById($id, ['status' => $status]);
        AuditService::log('question.status_changed', 'Question #' . $id . ' set to ' . $status, 'question', $id);
        $this->success('Question is now ' . $status . '.');
        return $this->back('/admin/questions');
    }

    // -----------------------------------------------------------------
    // Bulk import / export
    // -----------------------------------------------------------------

    public function importForm(Request $request): Response
    {
        return $this->view('admin.questions.import', ['categories' => $this->categories()]);
    }

    public function import(Request $request): Response
    {
        $file = $request->file('csv');
        if ($file === null) {
            $this->error('Choose a CSV file to import.');
            return $this->redirect('/admin/questions/import');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $this->error('The CSV file may not be larger than 5 MB.');
            return $this->redirect('/admin/questions/import');
        }

        $handle = @fopen((string) $file['tmp_name'], 'r');
        if ($handle === false) {
            $this->error('The uploaded file could not be read.');
            return $this->redirect('/admin/questions/import');
        }

        $categories = [];
        foreach ($this->categories() as $category) {
            $categories[mb_strtolower((string) $category['name'])] = (int) $category['id'];
        }

        $header = Csv::get($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('The CSV file is empty.');
            return $this->redirect('/admin/questions/import');
        }
        // Strip a UTF-8 BOM if Excel added one.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? $header[0];
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);

        $required = ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                fclose($handle);
                $this->error('The CSV is missing the required "' . $column . '" column.');
                return $this->redirect('/admin/questions/import');
            }
        }

        $imported = 0;
        $skipped = 0;
        $rowNumber = 1;
        $defaultTime = SettingsService::int('default_time_limit', 30);

        while (($row = Csv::get($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $record = [];
            foreach ($header as $index => $column) {
                $record[$column] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $correct = strtoupper($record['correct']);
            if ($record['question'] === '' || !in_array($correct, ['A', 'B', 'C', 'D'], true)) {
                $skipped++;
                continue;
            }

            $categoryId = null;
            if (($record['category'] ?? '') !== '') {
                $categoryId = $categories[mb_strtolower($record['category'])] ?? null;
            }

            $difficulty = strtolower($record['difficulty'] ?? '');
            if (!in_array($difficulty, ['easy', 'medium', 'hard', 'expert'], true)) {
                $difficulty = 'medium';
            }

            $this->questions->createWithOptions([
                'category_id'       => $categoryId,
                'question_text'     => $record['question'],
                'correct_option'    => $correct,
                'explanation'       => $record['explanation'] ?? null,
                'difficulty'        => $difficulty,
                'time_limit'        => (int) ($record['time_limit'] ?? 0) ?: $defaultTime,
                'prize_level'       => ($record['prize_level'] ?? '') === '' ? null : (int) $record['prize_level'],
                'lifelines_allowed' => ($record['lifelines'] ?? '1') === '0' ? 0 : 1,
                'status'            => ($record['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
                'sort_order'        => (int) ($record['sort_order'] ?? 0),
                'created_by'        => AuthService::id(),
            ], [
                'A' => $record['option_a'],
                'B' => $record['option_b'],
                'C' => $record['option_c'],
                'D' => $record['option_d'],
            ]);
            $imported++;
        }
        fclose($handle);

        AuditService::log('question.imported', 'Imported ' . $imported . ' question(s) from CSV.', 'question');
        $this->success($imported . ' question(s) imported.' . ($skipped > 0 ? ' ' . $skipped . ' row(s) were skipped as invalid.' : ''));
        return $this->redirect('/admin/questions');
    }

    public function export(Request $request): Response
    {
        $rows = Database::instance()->select(
            'SELECT q.id, c.name AS category, q.question_text, q.correct_option, q.explanation,
                    q.difficulty, q.time_limit, q.prize_level, q.lifelines_allowed, q.status, q.sort_order
             FROM questions q LEFT JOIN question_categories c ON c.id = q.category_id
             ORDER BY q.sort_order, q.id'
        );

        $options = [];
        foreach (Database::instance()->select('SELECT question_id, option_key, option_text FROM question_options') as $row) {
            $options[(int) $row['question_id']][(string) $row['option_key']] = (string) $row['option_text'];
        }

        $handle = fopen('php://temp', 'r+');
        // BOM so Excel opens Gujarati/Hindi text correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        Csv::put($handle, [
            'id', 'category', 'question', 'option_a', 'option_b', 'option_c', 'option_d',
            'correct', 'explanation', 'difficulty', 'time_limit', 'prize_level', 'lifelines', 'status', 'sort_order',
        ]);

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            Csv::put($handle, [
                $id,
                $row['category'] ?? '',
                $row['question_text'],
                $options[$id]['A'] ?? '',
                $options[$id]['B'] ?? '',
                $options[$id]['C'] ?? '',
                $options[$id]['D'] ?? '',
                $row['correct_option'],
                $row['explanation'] ?? '',
                $row['difficulty'],
                $row['time_limit'],
                $row['prize_level'] ?? '',
                $row['lifelines_allowed'],
                $row['status'],
                $row['sort_order'],
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        AuditService::log('question.exported', 'Exported ' . count($rows) . ' question(s) to CSV.', 'question');

        return Response::make($csv)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="questions-' . date('Y-m-d') . '.csv"');
    }

    // -----------------------------------------------------------------

    /** @return array<string,mixed> */
    private function validatePayload(Request $request): array
    {
        $data = Validator::validate($request->all(), [
            'question_text'     => 'required|string|max:2000',
            'correct_option'    => 'required|in:A,B,C,D',
            'category_id'       => 'nullable|int',
            'explanation'       => 'nullable|string|max:2000',
            'difficulty'        => 'required|in:easy,medium,hard,expert',
            'time_limit'        => 'required|int|between:5,600',
            'prize_level'       => 'nullable|int|between:1,999',
            'sort_order'        => 'nullable|int|between:0,999999',
        ], [
            'question_text'  => 'Question',
            'correct_option' => 'Correct answer',
            'time_limit'     => 'Time limit',
            'prize_level'    => 'Prize level',
        ]);

        $options = $this->optionPayload($request);
        foreach (['A', 'B', 'C', 'D'] as $key) {
            if (trim($options[$key]) === '') {
                throw new \App\Core\Exceptions\ValidationException(
                    ['option_' . $key => 'Option ' . $key . ' is required.'],
                    'All four options must be filled in.'
                );
            }
        }

        return [
            'question_text'     => $data['question_text'],
            'correct_option'    => $data['correct_option'],
            'category_id'       => ($data['category_id'] ?? 0) > 0 ? (int) $data['category_id'] : null,
            'explanation'       => $data['explanation'] ?? null,
            'difficulty'        => $data['difficulty'],
            'time_limit'        => (int) $data['time_limit'],
            'prize_level'       => ($data['prize_level'] ?? 0) > 0 ? (int) $data['prize_level'] : null,
            'lifelines_allowed' => $request->bool('lifelines_allowed', false) ? 1 : 0,
            'status'            => $request->bool('status', false) ? 'active' : 'inactive',
            'sort_order'        => (int) ($data['sort_order'] ?? 0),
        ];
    }

    /** @return array<string,string> */
    private function optionPayload(Request $request): array
    {
        return [
            'A' => $request->string('option_a'),
            'B' => $request->string('option_b'),
            'C' => $request->string('option_c'),
            'D' => $request->string('option_d'),
        ];
    }

    /**
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private function handleMedia(Request $request, ?array $existing): array
    {
        $out = [];
        $map = [
            'image' => ['types' => ['image'], 'max' => 4 * 1024 * 1024],
            'audio' => ['types' => ['audio'], 'max' => 10 * 1024 * 1024],
            'video' => ['types' => ['video'], 'max' => 25 * 1024 * 1024],
        ];

        foreach ($map as $field => $rules) {
            $file = $request->file($field);
            if ($file === null) {
                continue;
            }
            $stored = Uploader::store($file, 'questions', $rules['types'], $rules['max']);
            $out[$field . '_path'] = $stored['url'];

            if ($existing !== null) {
                Uploader::delete((string) ($existing[$field . '_path'] ?? ''));
            }

            Database::instance()->insert('media', [
                'disk_path'     => $stored['url'],
                'original_name' => substr((string) $file['name'], 0, 255),
                'mime_type'     => $stored['mime'],
                'extension'     => $stored['extension'],
                'size_bytes'    => $stored['size'],
                'type'          => $field,
                'collection'    => 'questions',
                'uploaded_by'   => AuthService::id(),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function categories(): array
    {
        return Database::instance()->select(
            "SELECT id, name, colour FROM question_categories WHERE status = 'active' ORDER BY sort_order, name"
        );
    }
}
