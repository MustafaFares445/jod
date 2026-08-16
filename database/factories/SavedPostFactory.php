<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedPost>
 */
class SavedPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array{user_id: UserFactory, post_id: PostFactory}
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory()->published(),
        ];
    }
}
