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
            'name' => 'تصنيف '.$this->faker->unique()->numberBetween(1000, 9999999),
            'description' => 'وصف تجريبي باللغة العربية للتصنيف المستخدم في بيانات الاختبار.',
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'usage_count' => $this->faker->numberBetween(0, 20),
        ];
    }
}
