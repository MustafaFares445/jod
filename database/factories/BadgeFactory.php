<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(10),
            'criteria' => $this->faker->paragraph(3),
            'icon_name' => $this->faker->word() . '_icon',
            'is_active' => $this->faker->boolean(80),
        ];
    }
}
