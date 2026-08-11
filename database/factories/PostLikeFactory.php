<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostLike>
 */
class PostLikeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::query()->create([
                'id' => (string) Str::uuid(),
                'title' => fake()->sentence(),
                'summary' => fake()->sentence(),
                'content' => fake()->paragraph(),
                'type' => 'help_request',
                'status' => 'published',
                'location' => fake()->city(),
                'published_at' => now(),
            ])->id,
        ];
    }
}
