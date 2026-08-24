<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'health' => 'Health and medical support campaigns.',
            'education' => 'Education and learning support campaigns.',
            'food' => 'Food security and meal support campaigns.',
            'shelter' => 'Housing and shelter support campaigns.',
            'employment' => 'Employment and livelihood support campaigns.',
            'emergency' => 'Emergency relief campaigns.',
            'donation' => 'General donation campaigns.',
            'volunteer' => 'Volunteer-driven campaigns.',
            'community' => 'Community support and development campaigns.',
        ];

        foreach ($categories as $name => $description) {
            Category::query()->updateOrCreate(
                ['name' => $name],
                [
                    'target' => 'campaign',
                    'description' => $description,
                    'status' => 'active',
                    'usage_count' => 0,
                ],
            );
        }
    }
}
