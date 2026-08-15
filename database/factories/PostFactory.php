<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array{id: string, title: string, summary: string, content: string, type: string, status: string, location: string, published_at: null}
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'type' => 'help_request',
            'status' => 'draft',
            'location' => fake()->city(),
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the post is published.
     */
    public function published(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
