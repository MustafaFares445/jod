<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'target' => $this->faker->randomElement(['post', 'campaign']),
            'description' => $this->faker->sentence(12),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'usage_count' => $this->faker->numberBetween(0, 20),
        ];
    }
}
