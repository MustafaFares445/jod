<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('generic recommendation feedback records interested and not interested post signals', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    $post = Post::factory()->published()->create(['category_id' => $category->id]);
    Sanctum::actingAs($user);

    $this->postJson('/api/mobile/recommendation-feedback', [
        'contentType' => 'post',
        'contentId' => $post->id,
        'action' => 'interested',
    ])->assertOk()->assertJsonPath('data.saved', true);

    $this->postJson('/api/mobile/recommendation-feedback', [
        'contentType' => 'post',
        'contentId' => $post->id,
        'action' => 'not_interested',
    ])->assertOk();

    $this->assertDatabaseHas('post_feedback', [
        'user_id' => $user->id,
        'post_id' => $post->id,
        'type' => 'not_interested',
    ]);
});
