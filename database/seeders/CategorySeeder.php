<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Str;

final class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['General Knowledge', '#d97706'],
            ['Ganpati Bapa', '#b3141a'],
            ['Gujarat', '#0f766e'],
            ['India', '#1d4ed8'],
            ['Religion & Culture', '#7c3aed'],
            ['History', '#92400e'],
            ['Science', '#0891b2'],
            ['Technology', '#334155'],
            ['Sports', '#15803d'],
            ['Current Affairs', '#be123c'],
            ['Dwarka', '#c2410c'],
        ];

        $order = 0;
        foreach ($categories as [$name, $colour]) {
            $order += 10;
            $this->firstOrCreate('question_categories', ['slug' => Str::slug($name)], [
                'name'       => $name,
                'colour'     => $colour,
                'status'     => 'active',
                'sort_order' => $order,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }
}
