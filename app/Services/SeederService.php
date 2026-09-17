<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LifelineSeeder;
use Database\Seeders\PrizeLevelSeeder;
use Database\Seeders\QuestionBankSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;

final class SeederService
{
    public function __construct(private Database $db)
    {
    }

    public static function make(?Database $db = null): self
    {
        return new self($db ?? Database::instance());
    }

    /** Seeders that every installation needs. */
    public function seedCore(): void
    {
        foreach ([RoleSeeder::class, SettingsSeeder::class, PrizeLevelSeeder::class, LifelineSeeder::class, CategorySeeder::class] as $class) {
            (new $class($this->db))->run();
        }
    }

    /** Optional sample content. */
    public function seedDemo(): void
    {
        (new DemoSeeder($this->db))->run();
    }

    /**
     * The ready-made Gujarati question bank: 175 questions across five
     * categories. Existing questions are never touched.
     */
    public function seedQuestionBank(): int
    {
        $before = (int) ($this->db->scalar('SELECT COUNT(*) FROM questions') ?? 0);
        (new QuestionBankSeeder($this->db))->run();
        $after = (int) ($this->db->scalar('SELECT COUNT(*) FROM questions') ?? 0);

        return $after - $before;
    }

    /**
     * Remove demo content without touching real data. Only rows that are
     * still untouched (no games played against them) are deleted.
     */
    public function removeDemo(): int
    {
        $removed = 0;

        $this->db->transaction(function (Database $db) use (&$removed): void {
            $demoParticipants = $db->select(
                "SELECT id FROM participants WHERE registration_no IN ('GQ-001','GQ-002','GQ-003')"
            );
            foreach ($demoParticipants as $row) {
                $id = (int) $row['id'];
                $used = (int) ($db->scalar('SELECT COUNT(*) FROM games WHERE participant_id = ?', [$id]) ?? 0);
                if ($used === 0) {
                    $removed += $db->delete('participants', ['id' => $id]);
                }
            }

            $demoQuestions = $db->select('SELECT id FROM questions WHERE times_used = 0');
            foreach ($demoQuestions as $row) {
                $id = (int) $row['id'];
                $used = (int) ($db->scalar('SELECT COUNT(*) FROM game_questions WHERE question_id = ?', [$id]) ?? 0);
                if ($used === 0) {
                    $removed += $db->delete('questions', ['id' => $id]);
                }
            }

            $demoGifts = $db->select('SELECT id FROM gifts WHERE quantity_used = 0');
            foreach ($demoGifts as $row) {
                $id = (int) $row['id'];
                $used = (int) ($db->scalar('SELECT COUNT(*) FROM game_answers WHERE gift_id = ?', [$id]) ?? 0);
                if ($used === 0) {
                    $db->run('UPDATE prize_levels SET gift_id = NULL WHERE gift_id = ?', [$id]);
                    $removed += $db->delete('gifts', ['id' => $id]);
                }
            }
        });

        return $removed;
    }
}
