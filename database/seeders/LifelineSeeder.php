<?php
declare(strict_types=1);

namespace Database\Seeders;

final class LifelineSeeder extends Seeder
{
    public function run(): void
    {
        $lifelines = [
            [
                'code'        => 'fifty_fifty',
                'name'        => '50:50',
                'description' => 'Removes two incorrect options, leaving the correct answer and one wrong option.',
                'icon'        => '50:50',
                'uses'        => 1,
                'order'       => 10,
                'config'      => ['keep_options' => 2],
            ],
            [
                'code'        => 'audience_poll',
                'name'        => 'Audience Poll',
                'description' => 'Shows a poll result bar for A, B, C and D.',
                'icon'        => 'poll',
                'uses'        => 1,
                'order'       => 20,
                'config'      => [
                    'mode'               => 'realistic',
                    'correct_bias_min'   => 45,
                    'correct_bias_max'   => 75,
                    'manual_percentages' => ['A' => 25, 'B' => 25, 'C' => 25, 'D' => 25],
                ],
            ],
            [
                'code'        => 'expert_advice',
                'name'        => 'Expert Advice',
                'description' => 'Shows an expert opinion with a confidence level.',
                'icon'        => 'expert',
                'uses'        => 1,
                'order'       => 30,
                'config'      => [
                    'expert_name'      => 'Quiz Expert',
                    'expert_photo'     => '',
                    'mode'             => 'auto',
                    'suggested_option' => '',
                    'confidence'       => 80,
                    'message'          => 'I am fairly confident about this one.',
                ],
            ],
            [
                'code'        => 'skip_question',
                'name'        => 'Skip Question',
                'description' => 'Skips the current question and moves to the next one at the same level.',
                'icon'        => 'skip',
                'uses'        => 1,
                'order'       => 40,
                'config'      => ['keep_prize' => true],
            ],
        ];

        foreach ($lifelines as $lifeline) {
            $this->firstOrCreate('lifelines', ['code' => $lifeline['code']], [
                'name'          => $lifeline['name'],
                'description'   => $lifeline['description'],
                'icon'          => $lifeline['icon'],
                'is_enabled'    => $lifeline['code'] === 'skip_question' ? 0 : 1,
                'uses_per_game' => $lifeline['uses'],
                'config'        => json_encode($lifeline['config'], JSON_UNESCAPED_UNICODE),
                'sort_order'    => $lifeline['order'],
                'created_at'    => $this->now,
                'updated_at'    => $this->now,
            ]);
        }
    }
}
