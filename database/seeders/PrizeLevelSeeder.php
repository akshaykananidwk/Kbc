<?php
declare(strict_types=1);

namespace Database\Seeders;

/**
 * Default 10 step ladder. Every value is editable from the admin panel -
 * this is only a starting point so a fresh install is immediately playable.
 */
final class PrizeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [1,  500,     0, 30],
            [2,  1000,    0, 30],
            [3,  2000,    0, 30],
            [4,  5000,    0, 45],
            [5,  10000,   1, 45],
            [6,  20000,   0, 45],
            [7,  40000,   0, 60],
            [8,  80000,   0, 60],
            [9,  160000,  0, 90],
            [10, 320000,  1, 90],
        ];

        foreach ($levels as [$no, $amount, $guaranteed, $time]) {
            $this->firstOrCreate('prize_levels', ['level_no' => $no], [
                'amount'        => $amount,
                'label'         => 'Question ' . $no,
                'is_guaranteed' => $guaranteed,
                'time_limit'    => $time,
                'difficulty'    => 'any',
                'status'        => 'active',
                'created_at'    => $this->now,
                'updated_at'    => $this->now,
            ]);
        }
    }
}
