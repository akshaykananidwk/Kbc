<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Application;
use App\Support\Str;

/**
 * The ready-made Gujarati question bank: five categories, 35 questions each.
 *
 * Safe to run again - a question already in the database is left alone, and
 * anything the host has edited is never overwritten.
 */
final class QuestionBankSeeder extends Seeder
{
    /** Category name => colour, in the order they should appear. */
    private const CATEGORIES = [
        'ધાર્મિકતા'      => '#b3141a',
        'દેશભક્તિ'       => '#15803d',
        'ભારતદર્શન'      => '#1d4ed8',
        'કરંટ અફેર્સ'    => '#be123c',
        'જનરલ નોલેજ'    => '#d97706',
    ];

    /**
     * Where the sample categories belong once the real bank is in place.
     * Only questions nobody has used yet are moved, and a category is only
     * retired once it is empty - a host's own work is never touched.
     */
    private const DEMO_CATEGORY_MAP = [
        'ganpati-bapa'      => 'ધાર્મિકતા',
        'religion-culture'  => 'ધાર્મિકતા',
        'gujarat'           => 'ભારતદર્શન',
        'india'             => 'ભારતદર્શન',
        'dwarka'            => 'ભારતદર્શન',
        'history'           => 'ભારતદર્શન',
        'science'           => 'જનરલ નોલેજ',
        'technology'        => 'જનરલ નોલેજ',
        'sports'            => 'જનરલ નોલેજ',
        'general-knowledge' => 'જનરલ નોલેજ',
        'current-affairs'   => 'કરંટ અફેર્સ',
    ];

    /** Which bank to load: 'open' (suits every age) or 'senior'. */
    private string $bank = 'open';

    public function useBank(string $bank): self
    {
        $this->bank = $bank === 'senior' ? 'senior' : 'open';
        return $this;
    }

    public function run(): void
    {
        $file = Application::instance()->rootPath(
            $this->bank === 'senior'
                ? 'database/seeds/gujarati-question-bank-senior.php'
                : 'database/seeds/gujarati-question-bank.php'
        );
        if (!is_file($file)) {
            return;
        }

        /** @var array<int,array{0:string,1:string,2:string,3:string,4:string,5:string,6:string,7:string,8:string}> $rows */
        $rows = require $file;

        $categoryIds = [];
        $order = 100;
        foreach (self::CATEGORIES as $name => $colour) {
            $order += 10;
            $id = $this->firstOrCreate('question_categories', ['slug' => Str::slug($name)], [
                'name'       => $name,
                'colour'     => $colour,
                'status'     => 'active',
                'sort_order' => $order,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            // These five are the show's rotation.
            $this->db->update('question_categories', [
                'status'      => 'active',
                'in_rotation' => 1,
                'updated_at'  => $this->now,
            ], ['id' => $id]);

            $categoryIds[$name] = $id;
        }

        $this->tidyDemoCategories($categoryIds);

        $sort = 0;
        foreach ($rows as $row) {
            [$category, $difficulty, $question, $a, $b, $c, $d, $correct, $explanation] = $row;
            $sort++;

            $existing = $this->db->selectOne(
                'SELECT id, category_id, times_used, times_served FROM questions WHERE question_text = ? LIMIT 1',
                [$question]
            );
            if ($existing !== null) {
                // The same question already shipped as demo content. Rather
                // than duplicate it, adopt it into its proper category - but
                // only while it is untouched, so nothing a host has already
                // used in a show is ever changed.
                $untouched = (int) $existing['times_used'] === 0 && (int) ($existing['times_served'] ?? 0) === 0;
                $wrongCategory = (int) $existing['category_id'] !== ($categoryIds[$category] ?? 0);

                if ($untouched && $wrongCategory) {
                    $this->db->update('questions', [
                        'category_id' => $categoryIds[$category] ?? null,
                        'difficulty'  => $difficulty,
                        'updated_at'  => $this->now,
                    ], ['id' => (int) $existing['id']]);
                }
                continue;
            }

            $questionId = $this->db->insert('questions', [
                'category_id'       => $categoryIds[$category] ?? null,
                'question_text'     => $question,
                'correct_option'    => $correct,
                'explanation'       => $explanation,
                'difficulty'        => $difficulty,
                // Senior questions are only ever asked of the senior half.
                'age_group'         => $this->bank === 'senior' ? 'senior' : 'any',
                'time_limit'        => 30,
                'prize_level'       => null,
                'lifelines_allowed' => 1,
                'status'            => 'active',
                'sort_order'        => $sort,
                'created_at'        => $this->now,
                'updated_at'        => $this->now,
            ]);

            foreach (['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d] as $index => $text) {
                $this->db->insert('question_options', [
                    'question_id' => $questionId,
                    'option_key'  => $index,
                    'option_text' => $text,
                    'sort_order'  => ord($index) - 65,
                ]);
            }
        }
    }

    /**
     * Folds the sample categories into the five the show uses, so a balanced
     * game really can ask two questions from each.
     *
     * @param array<string,int> $categoryIds
     */
    private function tidyDemoCategories(array $categoryIds): void
    {
        foreach (self::DEMO_CATEGORY_MAP as $slug => $target) {
            $category = $this->db->selectOne(
                'SELECT id FROM question_categories WHERE slug = ? LIMIT 1',
                [$slug]
            );
            if ($category === null || !isset($categoryIds[$target])) {
                continue;
            }
            $categoryId = (int) $category['id'];

            $this->db->run(
                'UPDATE questions SET category_id = ?, updated_at = ?
                 WHERE category_id = ? AND times_used = 0 AND times_served = 0',
                [$categoryIds[$target], $this->now, $categoryId]
            );

            $left = (int) ($this->db->scalar(
                'SELECT COUNT(*) FROM questions WHERE category_id = ?',
                [$categoryId]
            ) ?? 0);

            $this->db->update('question_categories', [
                // An empty sample category is retired; one that still holds a
                // question the host has used stays, but out of the rotation so
                // a balanced show keeps asking two from each of the five.
                'status'      => $left === 0 ? 'inactive' : 'active',
                'in_rotation' => 0,
                'updated_at'  => $this->now,
            ], ['id' => $categoryId]);
        }
    }
}
