<?php
declare(strict_types=1);

namespace App\Repositories;

final class QuestionRepository extends Repository
{
    protected string $table = 'questions';

    /** @return array<string,mixed>|null Question row with its four options attached. */
    public function findWithOptions(int $id): ?array
    {
        $question = $this->db->selectOne(
            'SELECT q.*, c.name AS category_name, c.colour AS category_colour
             FROM questions q LEFT JOIN question_categories c ON c.id = q.category_id
             WHERE q.id = ? LIMIT 1',
            [$id]
        );
        if ($question === null) {
            return null;
        }
        $question['options'] = $this->options($id);
        return $question;
    }

    /** @return array<string,string> Keyed by A/B/C/D. */
    public function options(int $questionId): array
    {
        $rows = $this->db->select(
            'SELECT option_key, option_text FROM question_options WHERE question_id = ? ORDER BY option_key',
            [$questionId]
        );
        $options = [];
        foreach ($rows as $row) {
            $options[(string) $row['option_key']] = (string) $row['option_text'];
        }
        return $options;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $options
     */
    public function createWithOptions(array $data, array $options): int
    {
        return $this->db->transaction(function () use ($data, $options): int {
            $id = $this->create($data);
            foreach (['A', 'B', 'C', 'D'] as $index => $key) {
                $this->db->insert('question_options', [
                    'question_id' => $id,
                    'option_key'  => $key,
                    'option_text' => (string) ($options[$key] ?? ''),
                    'sort_order'  => $index,
                ]);
            }
            return $id;
        });
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $options
     */
    public function updateWithOptions(int $id, array $data, array $options): void
    {
        $this->db->transaction(function () use ($id, $data, $options): void {
            $this->updateById($id, $data);
            foreach (['A', 'B', 'C', 'D'] as $index => $key) {
                $existing = $this->db->selectOne(
                    'SELECT id FROM question_options WHERE question_id = ? AND option_key = ? LIMIT 1',
                    [$id, $key]
                );
                $text = (string) ($options[$key] ?? '');
                if ($existing === null) {
                    $this->db->insert('question_options', [
                        'question_id' => $id,
                        'option_key'  => $key,
                        'option_text' => $text,
                        'sort_order'  => $index,
                    ]);
                } else {
                    $this->db->update('question_options', ['option_text' => $text], ['id' => (int) $existing['id']]);
                }
            }
        });
    }

    public function duplicate(int $id, ?int $userId): ?int
    {
        $question = $this->findWithOptions($id);
        if ($question === null) {
            return null;
        }
        $options = $question['options'];
        unset($question['id'], $question['options'], $question['category_name'], $question['category_colour']);

        $question['question_text'] = '[Copy] ' . $question['question_text'];
        $question['status'] = 'inactive';
        $question['prize_level'] = null;
        $question['times_used'] = 0;
        $question['times_correct'] = 0;
        $question['times_wrong'] = 0;
        $question['created_by'] = $userId;

        return $this->createWithOptions($question, $options);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(q.question_text LIKE :search OR q.explanation LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if ((int) ($filters['category_id'] ?? 0) > 0) {
            $where[] = 'q.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }
        if (($filters['difficulty'] ?? '') !== '') {
            $where[] = 'q.difficulty = :difficulty';
            $bindings['difficulty'] = $filters['difficulty'];
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'q.status = :status';
            $bindings['status'] = $filters['status'];
        }
        if ((int) ($filters['prize_level'] ?? 0) > 0) {
            $where[] = 'q.prize_level = :prize_level';
            $bindings['prize_level'] = (int) $filters['prize_level'];
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->db->scalar('SELECT COUNT(*) FROM questions q' . $clause, $bindings) ?? 0);

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $sort = $this->safeOrder((string) ($filters['sort'] ?? 'q.sort_order ASC, q.id ASC'));

        $rows = $this->db->select(
            'SELECT q.*, c.name AS category_name, c.colour AS category_colour
             FROM questions q LEFT JOIN question_categories c ON c.id = q.category_id'
            . $clause . ' ORDER BY ' . $sort . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $bindings
        );

        foreach ($rows as $i => $row) {
            $rows[$i]['options'] = $this->options((int) $row['id']);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Choose a question for a level, honouring the configured ordering mode
     * and never repeating a question inside the same game.
     *
     * @param array<int,int> $excludeIds
     */
    public function pickForLevel(
        int $levelNo,
        string $mode,
        ?int $categoryId,
        string $difficulty,
        array $excludeIds,
        bool $allowReuseAcrossGames
    ): ?array {
        $bindings = [];
        $where = ["q.status = 'active'"];

        if ($excludeIds !== []) {
            $placeholders = [];
            foreach (array_values($excludeIds) as $i => $id) {
                $key = 'ex' . $i;
                $placeholders[] = ':' . $key;
                $bindings[$key] = (int) $id;
            }
            $where[] = 'q.id NOT IN (' . implode(',', $placeholders) . ')';
        }

        if (!$allowReuseAcrossGames) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM game_questions gq WHERE gq.question_id = q.id)';
        }

        // Preferred: a question explicitly pinned to this level.
        $pinned = $this->db->selectOne(
            'SELECT q.* FROM questions q WHERE ' . implode(' AND ', $where)
            . ' AND q.prize_level = :level ORDER BY q.sort_order ASC, q.id ASC LIMIT 1',
            $bindings + ['level' => $levelNo]
        );
        if ($pinned !== null) {
            $pinned['options'] = $this->options((int) $pinned['id']);
            return $pinned;
        }

        // Otherwise fall back to the ordering mode.
        $modeWhere = $where;
        $modeBindings = $bindings;
        $modeWhere[] = '(q.prize_level IS NULL OR q.prize_level = :level2)';
        $modeBindings['level2'] = $levelNo;

        if ($mode === 'category' && $categoryId !== null && $categoryId > 0) {
            $modeWhere[] = 'q.category_id = :cat';
            $modeBindings['cat'] = $categoryId;
        }
        if ($mode === 'difficulty' && $difficulty !== '' && $difficulty !== 'any') {
            $modeWhere[] = 'q.difficulty = :diff';
            $modeBindings['diff'] = $difficulty;
        }

        $order = $mode === 'random' ? 'RAND()' : 'q.sort_order ASC, q.id ASC';

        $row = $this->db->selectOne(
            'SELECT q.* FROM questions q WHERE ' . implode(' AND ', $modeWhere) . ' ORDER BY ' . $order . ' LIMIT 1',
            $modeBindings
        );

        // Last resort: ignore the level/category/difficulty constraints so the
        // show never stalls for lack of a perfectly matching question.
        if ($row === null) {
            $row = $this->db->selectOne(
                'SELECT q.* FROM questions q WHERE ' . implode(' AND ', $where)
                . ' ORDER BY ' . ($mode === 'random' ? 'RAND()' : 'q.sort_order ASC, q.id ASC') . ' LIMIT 1',
                $bindings
            );
        }

        if ($row === null) {
            return null;
        }
        $row['options'] = $this->options((int) $row['id']);
        return $row;
    }

    public function activeCount(): int
    {
        return $this->count("status = 'active'");
    }

    /** @return array<int,array<string,mixed>> */
    public function hardest(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT q.id, q.question_text, q.difficulty, q.times_used, q.times_correct, q.times_wrong,
                    ROUND(CASE WHEN q.times_used > 0 THEN (q.times_correct / q.times_used) * 100 ELSE 0 END, 1) AS success_rate
             FROM questions q WHERE q.times_used > 0
             ORDER BY success_rate ASC, q.times_used DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function mostUsed(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT id, question_text, difficulty, times_used, times_correct, times_wrong
             FROM questions WHERE times_used > 0 ORDER BY times_used DESC LIMIT ' . max(1, $limit)
        );
    }

    public function recordResult(int $questionId, bool $correct): void
    {
        $this->db->run(
            'UPDATE questions SET times_used = times_used + 1,
                times_correct = times_correct + :c, times_wrong = times_wrong + :w
             WHERE id = :id',
            ['c' => $correct ? 1 : 0, 'w' => $correct ? 0 : 1, 'id' => $questionId]
        );
    }
}
