<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Report;
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
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => 'other',
            'status' => 'new',
            'severity' => 'medium',
            'entity_type' => 'post',
            'entity_id' => (string) Str::uuid(),
            'evidence' => [],
            'timeline' => [],
        ];
    }
}
