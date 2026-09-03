<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\PublisherFollow;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserPreference;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('for you feed ranks followed and explicitly interesting content first', function () {
    $viewer = User::factory()->create(['city' => 'دمشق']);
    $followedAuthor = User::factory()->create();
    $otherAuthor = User::factory()->create();
    $education = Category::factory()->create(['status' => 'active']);
    $otherCategory = Category::factory()->create(['status' => 'active']);

    UserPreference::query()->create([
        'user_id' => $viewer->id,
        'intent' => 'giver',
        'preferred_city' => 'دمشق',
        'onboarding_completed_at' => now(),
    ]);
    UserCategoryInterest::query()->create([
        'user_id' => $viewer->id,
        'category_id' => $education->id,
        'explicit_weight' => 10,
        'behavioral_weight' => 0,
    ]);
    PublisherFollow::query()->create([
        'follower_user_id' => $viewer->id,
        'target_type' => 'user',
        'target_id' => $followedAuthor->id,
        'notification_level' => 'all',
    ]);

    $preferred = Post::factory()->published()->create([
        'author_id' => $followedAuthor->id,
        'category_id' => $education->id,
        'location' => 'دمشق',
        'type' => 'help_request',
        'published_at' => now()->subHour(),
    ]);
    Post::factory()->published()->create([
        'author_id' => $otherAuthor->id,
        'category_id' => $otherCategory->id,
        'location' => 'حمص',
        'type' => 'awareness',
        'published_at' => now(),
    ]);

    Sanctum::actingAs($viewer);
    $response = $this->getJson('/api/mobile/feed?type=for_you&perPage=20');

    $response->assertOk()
        ->assertJsonPath('meta.feedType', 'for_you')
        ->assertJsonPath('data.0.content.id', $preferred->id)
        ->assertJsonFragment(['reasons' => ['followed_publisher', 'explicit_interest', 'same_city']]);
});

test('nearby feed only returns active unexpired content in preferred city', function () {
    $viewer = User::factory()->create(['city' => 'دمشق']);
    $author = User::factory()->create();
    UserPreference::query()->create([
        'user_id' => $viewer->id,
        'intent' => 'both',
        'preferred_city' => 'دمشق',
        'onboarding_completed_at' => now(),
    ]);

    $nearby = Post::factory()->published()->create([
        'author_id' => $author->id,
        'location' => 'دمشق',
        'expires_at' => now()->addDay(),
    ]);
    Post::factory()->published()->create([
        'author_id' => $author->id,
        'location' => 'حلب',
    ]);
    Post::factory()->published()->create([
        'author_id' => $author->id,
        'location' => 'دمشق',
        'expires_at' => now()->subMinute(),
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson('/api/mobile/feed?type=nearby')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.content.id', $nearby->id);
});

test('urgent feed only returns important urgent or critical posts', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $urgent = Post::factory()->published()->create([
        'author_id' => $author->id,
        'urgency' => 'urgent',
    ]);
    Post::factory()->published()->create([
        'author_id' => $author->id,
        'urgency' => 'normal',
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson('/api/mobile/feed?type=urgent')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.content.id', $urgent->id)
        ->assertJsonPath('data.0.content.urgency', 'urgent');
});

test('not interested feedback removes post from personalized feed and records interaction', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    $post = Post::factory()->published()->create([
        'author_id' => $author->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($viewer);

    $this->postJson("/api/mobile/posts/{$post->id}/not-interested")
        ->assertOk()
        ->assertJsonPath('data.isNotInterested', true);

    $this->getJson('/api/mobile/feed?type=for_you')
        ->assertOk()
        ->assertJsonMissing(['id' => $post->id]);

    $this->assertDatabaseHas('post_feedback', [
        'user_id' => $viewer->id,
        'post_id' => $post->id,
        'type' => 'not_interested',
    ]);
    $this->assertDatabaseHas('user_interactions', [
        'user_id' => $viewer->id,
        'subject_id' => $post->id,
        'event_type' => 'not_interested',
    ]);
});

test('meaningful post views update behavioral category interest once per dedupe window', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    $post = Post::factory()->published()->create([
        'author_id' => $author->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($viewer);

    $this->postJson("/api/mobile/posts/{$post->id}/view", [
        'durationSeconds' => 3,
        'visiblePercent' => 90,
    ])->assertOk()->assertJsonPath('data.tracked', true);

    $this->postJson("/api/mobile/posts/{$post->id}/view", [
        'durationSeconds' => 4,
        'visiblePercent' => 100,
    ])->assertOk()->assertJsonPath('data.tracked', false);

    $this->assertDatabaseHas('user_category_interests', [
        'user_id' => $viewer->id,
        'category_id' => $category->id,
        'behavioral_weight' => 1,
    ]);
});
