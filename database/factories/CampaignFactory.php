<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'category' => 'health',
            'status' => 'draft',
            'location' => fake()->city(),
            'goal_amount' => 1000,
            'raised_amount' => 0,
            'beneficiaries_count' => 10,
            'donors_count' => 0,
            'applicants_count' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ];
    }
}
