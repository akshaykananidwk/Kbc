<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Hall of fame shown on the display screen between games.
 * Rehearsal games are never counted.
 */
final class LeaderboardService
{
    public function __construct(private Database $db)
    {
    }

    public static function make(?Database $db = null): self
    {
        return new self($db ?? Database::instance());
    }

    /** @return array<int,array<string,mixed>> */
    public function top(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));

        $rows = $this->db->select(
            "SELECT p.name, p.city, p.photo_path,
                    MAX(g.final_prize) AS best_prize,
                    MAX(g.questions_correct) AS best_questions,
                    COUNT(g.id) AS games_played,
                    MAX(g.ended_at) AS last_played
             FROM games g
             INNER JOIN participants p ON p.id = g.participant_id
             WHERE g.is_rehearsal = 0
               AND g.status IN ('completed','wrong_answer','time_up','quit')
             GROUP BY p.id, p.name, p.city, p.photo_path
             ORDER BY best_prize DESC, best_questions DESC, last_played ASC
             LIMIT " . $limit
        );

        $out = [];
        foreach ($rows as $index => $row) {
            $out[] = [
                'rank'       => $index + 1,
                'name'       => (string) $row['name'],
                'city'       => (string) ($row['city'] ?? ''),
                'photo'      => \App\Core\Application::uploadUrl($row['photo_path'] ?? null),
                'prize'      => (float) $row['best_prize'],
                'prize_label'=> SettingsService::money((float) $row['best_prize']),
                'questions'  => (int) $row['best_questions'],
                'games'      => (int) $row['games_played'],
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> Headline numbers for the idle screen. */
    public function summary(): array
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS games,
                    COUNT(DISTINCT participant_id) AS players,
                    COALESCE(SUM(final_prize), 0) AS total_prize,
                    COALESCE(MAX(final_prize), 0) AS top_prize
             FROM games
             WHERE is_rehearsal = 0 AND status IN ('completed','wrong_answer','time_up','quit')"
        ) ?? [];

        return [
            'games'            => (int) ($row['games'] ?? 0),
            'players'          => (int) ($row['players'] ?? 0),
            'total_prize'      => (float) ($row['total_prize'] ?? 0),
            'total_prize_label'=> SettingsService::money((float) ($row['total_prize'] ?? 0)),
            'top_prize_label'  => SettingsService::money((float) ($row['top_prize'] ?? 0)),
        ];
    }
}
