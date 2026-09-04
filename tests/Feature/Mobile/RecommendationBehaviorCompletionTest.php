<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function recommendationViewer(Category $interestCategory): User
{
    $viewer = User::factory()->create(['city' => 'دمشق']);
    UserPreference::query()->create([
        'user_id' => $viewer->id,
        'intent' => 'both',
        'preferred_city' => 'دمشق',
        'preferred_governorate' => 'دمشق',
        'availability_status' => 'available',
        'onboarding_completed_at' => now(),
    ]);
    UserCategoryInterest::query()->create([
        'user_id' => $viewer->id,
        'category_id' => $interestCategory->id,
        'explicit_weight' => 10,
        'behavioral_weight' => 0,
    ]);

    return $viewer;
}

function seedFeedPosts(Category $normalCategory, Category $explorationCategory): array
{
    $authors = User::factory()->count(6)->create();
    $normal = collect();
    $exploration = collect();

    foreach (range(0, 11) as $index) {
        $normal->push(Post::factory()->published()->create([
            'author_id' => $authors[$index % $authors->count()]->id,
            'category_id' => $normalCategory->id,
            'location' => 'دمشق',
            'published_at' => now()->subMinutes($index),
        ]));
    }

    foreach (range(0, 4) as $index) {
        $exploration->push(Post::factory()->published()->create([
            'author_id' => $authors[($index + 2) % $authors->count()]->id,
            'category_id' => $explorationCategory->id,
            'location' => 'دمشق',
            'published_at' => now()->subMinutes(20 + $index),
        ]));
    }

    return [$normal, $exploration];
}

test('like and save update behavioral interest only once for idempotent retries', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    $post = Post::factory()->published()->create([
        'author_id' => $author->id,
        'category_id' => $category->id,
    ]);

    Sanctum::actingAs($viewer);

    $this->postJson("/api/mobile/posts/{$post->id}/like")->assertOk();
    $this->postJson("/api/mobile/posts/{$post->id}/like")->assertOk();
    $this->postJson("/api/mobile/posts/{$post->id}/save")->assertOk();
    $this->postJson("/api/mobile/posts/{$post->id}/save")->assertOk();

    $interest = UserCategoryInterest::query()
        ->where('user_id', $viewer->id)
        ->where('category_id', $category->id)
        ->firstOrFail();

    expect((float) $interest->behavioral_weight)->toBe(8.0);
    expect(DB::table('user_interactions')->where('user_id', $viewer->id)->where('event_type', 'post_like')->count())->toBe(1);
    expect(DB::table('user_interactions')->where('user_id', $viewer->id)->where('event_type', 'post_save')->count())->toBe(1);
});

test('search aliases update category behavior once inside the dedupe window', function () {
    $viewer = User::factory()->create();
    $category = Category::factory()->create(['name' => 'التعليم', 'status' => 'active']);
    DB::table('category_keywords')->insert([
        'id' => (string) Str::uuid(),
        'category_id' => $category->id,
        'keyword' => 'مدرس',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson('/api/mobile/search?search='.urlencode('مدرس رياضيات'))->assertOk();
    $this->getJson('/api/mobile/search?search='.urlencode('مدرس رياضيات'))->assertOk();

    $interest = UserCategoryInterest::query()
        ->where('user_id', $viewer->id)
        ->where('category_id', $category->id)
        ->firstOrFail();

    expect((float) $interest->behavioral_weight)->toBe(4.0);
    expect(DB::table('user_interactions')->where('user_id', $viewer->id)->where('event_type', 'search')->count())->toBe(1);
});

test('post view requires both duration and visible percentage', function () {
    $viewer = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    $post = Post::factory()->published()->create(['category_id' => $category->id]);

    Sanctum::actingAs($viewer);

    $this->postJson("/api/mobile/posts/{$post->id}/view", [
        'durationSeconds' => 5,
        'visiblePercent' => 40,
    ])->assertOk()->assertJsonPath('data.tracked', false);

    $this->postJson("/api/mobile/posts/{$post->id}/view", [
        'durationSeconds' => 2,
        'visiblePercent' => 60,
    ])->assertOk()->assertJsonPath('data.tracked', true);

    expect(DB::table('user_interactions')->where('user_id', $viewer->id)->where('event_type', 'post_view')->count())->toBe(1);
});

test('for you injects a small controlled exploration set and prompt cooldown is respected', function () {
    $education = Category::factory()->create(['name' => 'التعليم', 'status' => 'active']);
    $food = Category::factory()->create(['name' => 'الغذاء', 'status' => 'active']);
    $viewer = recommendationViewer($education);
    seedFeedPosts($education, $food);

    Sanctum::actingAs($viewer);

    $first = $this->getJson('/api/mobile/feed?type=for_you&perPage=10')->assertOk();
    $items = collect($first->json('data'));
    $exploration = $items->filter(fn (array $item): bool => (bool) data_get($item, 'recommendation.isExploration'));

    expect($exploration->count())->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
    expect($exploration->every(fn (array $item): bool => in_array('discovery', data_get($item, 'recommendation.reasons', []), true)))->toBeTrue();
    expect($items->filter(fn (array $item): bool => data_get($item, 'recommendation.prompt.shouldAsk') === true)->count())->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(2);

    $second = $this->getJson('/api/mobile/feed?type=for_you&perPage=10')->assertOk();
    expect(collect($second->json('data'))->filter(fn (array $item): bool => data_get($item, 'recommendation.prompt.shouldAsk') === true)->count())->toBe(0);

    expect(DB::table('recommendation_impressions')->where('user_id', $viewer->id)->where('is_exploration', true)->count())->toBeGreaterThan(0);
});

test('exploration interested and not interested feedback changes future category behavior without retry inflation', function () {
    $education = Category::factory()->create(['name' => 'التعليم', 'status' => 'active']);
    $food = Category::factory()->create(['name' => 'الغذاء', 'status' => 'active']);
    $viewer = recommendationViewer($education);
    [, $explorationPosts] = seedFeedPosts($education, $food);

    Sanctum::actingAs($viewer);

    $feed = $this->getJson('/api/mobile/feed?type=for_you&perPage=10')->assertOk();
    $explorationItem = collect($feed->json('data'))->first(
        fn (array $item): bool => (bool) data_get($item, 'recommendation.isExploration')
    );

    expect($explorationItem)->not->toBeNull();
    $postId = (string) data_get($explorationItem, 'content.id');

    $payload = [
        'contentType' => 'post',
        'contentId' => $postId,
        'categoryId' => $food->id,
        'response' => 'interested',
    ];

    $this->postJson('/api/mobile/recommendations/exploration-feedback', $payload)
        ->assertOk()
        ->assertJsonPath('data.behavioralWeight', 20);
    $this->postJson('/api/mobile/recommendations/exploration-feedback', $payload)
        ->assertOk()
        ->assertJsonPath('data.behavioralWeight', 20);

    $payload['response'] = 'not_interested';
    $this->postJson('/api/mobile/recommendations/exploration-feedback', $payload)
        ->assertOk()
        ->assertJsonPath('data.behavioralWeight', -20);

    $this->assertDatabaseHas('post_feedback', [
        'user_id' => $viewer->id,
        'post_id' => $postId,
        'type' => 'not_interested',
    ]);
    expect(DB::table('user_interactions')->where('user_id', $viewer->id)->where('event_type', 'exploration_not_interested')->where('subject_id', $postId)->count())->toBe(1);
});

test('exploration feedback is rejected when the content was not recently shown as exploration', function () {
    $viewer = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    $post = Post::factory()->published()->create(['category_id' => $category->id]);

    Sanctum::actingAs($viewer);

    $this->postJson('/api/mobile/recommendations/exploration-feedback', [
        'contentType' => 'post',
        'contentId' => $post->id,
        'categoryId' => $category->id,
        'response' => 'interested',
    ])->assertUnprocessable()->assertJsonValidationErrors('contentId');
});

test('urgent feed never marks content as exploration', function () {
    $viewer = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    Post::factory()->published()->create([
        'category_id' => $category->id,
        'urgency' => 'urgent',
    ]);

    Sanctum::actingAs($viewer);

    $response = $this->getJson('/api/mobile/feed?type=urgent')->assertOk();
    expect(collect($response->json('data'))->every(fn (array $item): bool => data_get($item, 'recommendation.isExploration') === false))->toBeTrue();
});

test('weekly decay reduces only behavioral interest and preserves explicit interest', function () {
    $viewer = User::factory()->create();
    $category = Category::factory()->create(['status' => 'active']);
    $interest = UserCategoryInterest::query()->create([
        'user_id' => $viewer->id,
        'category_id' => $category->id,
        'explicit_weight' => 10,
        'behavioral_weight' => 30,
    ]);
    DB::table('user_category_interests')->where('id', $interest->id)->update([
        'updated_at' => now()->subDays(8),
    ]);

    Artisan::call('jod:decay-behavioral-interests');

    $interest->refresh();
    expect((float) $interest->behavioral_weight)->toBe(24.0);
    expect((float) $interest->explicit_weight)->toBe(10.0);
});
