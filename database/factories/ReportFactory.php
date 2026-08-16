<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'category' => 'other',
            'status' => 'new',
            'severity' => 'medium',
            'organization_id' => Organization::factory(),
            'reporter_id' => User::factory(),
            'evidence' => [],
            'timeline' => [],
        ];
    }
}
