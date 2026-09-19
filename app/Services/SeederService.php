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
     * The ready-made Gujarati question bank: 200 questions across five
     * categories. Existing questions are never touched.
     */
    public function seedQuestionBank(string $bank = 'open'): int
    {
        $before = (int) ($this->db->scalar('SELECT COUNT(*) FROM questions') ?? 0);
        (new QuestionBankSeeder($this->db))->useBank($bank)->run();
        $after = (int) ($this->db->scalar('SELECT COUNT(*) FROM questions') ?? 0);

        return $after - $before;
    }

    /**
     * Clears the question bank so a fresh one can be loaded before a new event.
     *
     * Past games point at the questions they asked, so that history goes with
     * them - which is the point of a reset - and the caller is expected to
     * take a backup first. Settings, participants, prizes, gifts and users are
     * all left exactly as they are.
     *
     * @return array{questions:int,games:int}
     */
    public function clearQuestions(): array
    {
        $questions = (int) ($this->db->scalar('SELECT COUNT(*) FROM questions') ?? 0);
        $games = (int) ($this->db->scalar('SELECT COUNT(*) FROM games') ?? 0);

        $this->db->transaction(function (Database $db): void {
            // Everything that points at a question, innermost first.
            foreach ([
                'game_answers',
                'game_lifelines',
                'game_switched_questions',
                'game_questions',
                'game_events',
                'certificates',
                'audience_votes',
                'audience_polls',
                'games',
                'question_options',
                'questions',
            ] as $table) {
                $db->run('DELETE FROM ' . $table);
            }
            $db->run("UPDATE gifts SET quantity_used = 0, status = 'active'");
        });

        return ['questions' => $questions, 'games' => $games];
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
