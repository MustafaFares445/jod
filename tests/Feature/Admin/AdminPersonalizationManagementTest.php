<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Enums\PersonalizationEventType;
use App\Models\Capability;
use App\Models\Category;
use App\Models\Post;
use App\Models\RecommendationImpression;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserInteraction;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['user_type' => 'admin']);
    $this->grantPermissions($this->admin, [
        [PermissionGroup::CAPABILITY, PermissionAction::VIEW],
        [PermissionGroup::CAPABILITY, PermissionAction::CREATE],
        [PermissionGroup::CAPABILITY, PermissionAction::UPDATE],
        [PermissionGroup::CAPABILITY, PermissionAction::DELETE],
        [PermissionGroup::CATEGORY, PermissionAction::VIEW],
        [PermissionGroup::CATEGORY, PermissionAction::UPDATE],
        [PermissionGroup::PERSONALIZATION, PermissionAction::VIEW],
        [PermissionGroup::RECOMMENDATION, PermissionAction::VIEW],
        [PermissionGroup::RECOMMENDATION, PermissionAction::DIAGNOSTICS],
        [PermissionGroup::HELP_MATCHING, PermissionAction::VIEW],
        [PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_URGENCY],
        [PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_OUTCOMES],
    ]);
    Sanctum::actingAs($this->admin);
});

test('admin manages capabilities without resetting omitted fields', function () {
    $response = $this->postJson('/api/v1/admin/capabilities', [
        'name' => 'التدريس',
        'slug' => 'teaching',
        'status' => 'active',
        'sortOrder' => 10,
    ]);

    $response->assertSuccessful();
    $id = $response->json('data.id');
    expect($id)->not->toBeNull();

    $this->patchJson("/api/v1/admin/capabilities/{$id}", [
        'name' => 'التدريس الأكاديمي',
    ])->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.sortOrder', 10);

    $this->patchJson("/api/v1/admin/capabilities/{$id}/status", [
        'status' => 'inactive',
    ])->assertOk()->assertJsonPath('data.status', 'inactive');

    $this->getJson('/api/v1/admin/capabilities')->assertOk();
});

test('admin manages category search aliases', function () {
    $category = Category::factory()->create(['status' => 'active']);

    $this->putJson("/api/v1/admin/categories/{$category->id}/keywords", [
        'keywords' => ['تعليم', 'مدرس', 'جامعة', 'تعليم'],
    ])->assertOk()->assertJsonCount(3, 'data.keywords');

    $this->getJson("/api/v1/admin/categories/{$category->id}/keywords")
        ->assertOk()
        ->assertJsonPath('data.categoryId', $category->id)
        ->assertJsonCount(3, 'data.keywords');
});

test('admin views user personalization summary', function () {
    $user = User::factory()->create(['city' => 'دمشق']);
    $category = Category::factory()->create(['status' => 'active']);
    $capability = Capability::query()->create([
        'name' => 'النقل',
        'slug' => 'transport',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    UserPreference::query()->create([
        'user_id' => $user->id,
        'intent' => 'both',
        'preferred_city' => 'دمشق',
        'remote_help_enabled' => true,
        'availability_status' => 'weekends',
        'onboarding_completed_at' => now(),
    ]);
    UserCategoryInterest::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'explicit_weight' => 10,
        'behavioral_weight' => 5,
    ]);
    $user->capabilities()->attach($capability->id);

    $this->getJson("/api/v1/admin/users/{$user->id}/personalization")
        ->assertOk()
        ->assertJsonPath('data.intent', 'both')
        ->assertJsonPath('data.preferredCity', 'دمشق')
        ->assertJsonPath('data.capabilities.0.slug', 'transport');
});

test('admin manages help request urgency expiration and outcome', function () {
    $post = Post::factory()->create([
        'type' => 'help_request',
        'status' => 'published',
        'help_status' => 'open',
        'urgency' => 'normal',
    ]);

    $this->patchJson("/api/v1/admin/posts/{$post->id}/urgency", [
        'urgency' => 'critical',
        'reason' => 'حالة طبية حرجة',
    ])->assertOk()->assertJsonPath('data.urgency', 'critical');

    $this->patchJson("/api/v1/admin/posts/{$post->id}/expiration", [
        'expiresAt' => now()->addDay()->toIso8601String(),
    ])->assertOk();

    $this->patchJson("/api/v1/admin/posts/{$post->id}/fulfillment", [
        'status' => 'fulfilled',
    ])->assertOk()->assertJsonPath('data.helpStatus', 'fulfilled');

    $this->assertDatabaseHas('audit_logs', [
        'entity_type' => 'post',
        'entity_id' => $post->id,
    ]);
});

test('critical urgency requires an explanation', function () {
    $post = Post::factory()->create([
        'type' => 'help_request',
        'status' => 'published',
        'help_status' => 'open',
    ]);

    $this->patchJson("/api/v1/admin/posts/{$post->id}/urgency", [
        'urgency' => 'critical',
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
});

test('scheduled command expires elapsed help requests', function () {
    $post = Post::factory()->create([
        'type' => 'help_request',
        'status' => 'published',
        'help_status' => 'open',
        'expires_at' => now()->subMinute(),
    ]);

    Artisan::call('jod:expire-help-requests');

    expect($post->refresh()->help_status->value)->toBe('expired');
});

test('recommendation analytics attributes interactions and exploration feedback', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['status' => 'published']);

    RecommendationImpression::query()->create([
        'user_id' => $user->id,
        'subject_type' => 'post',
        'subject_id' => $post->id,
        'feed_type' => 'for_you',
        'score' => 50,
        'reasons' => ['discovery'],
        'is_exploration' => true,
        'shown_at' => now()->subSecond(),
    ]);
    UserInteraction::query()->create([
        'user_id' => $user->id,
        'event_type' => PersonalizationEventType::PostSave,
        'subject_type' => 'post',
        'subject_id' => $post->id,
        'occurred_at' => now(),
    ]);
    UserInteraction::query()->create([
        'user_id' => $user->id,
        'event_type' => PersonalizationEventType::ExplorationInterested,
        'subject_type' => 'post',
        'subject_id' => $post->id,
        'occurred_at' => now(),
    ]);

    $this->getJson('/api/v1/admin/recommendations/analytics?feedType=for_you')
        ->assertOk()
        ->assertJsonPath('data.summary.impressions', 1)
        ->assertJsonPath('data.summary.saves', 1)
        ->assertJsonPath('data.summary.explorationImpressions', 1)
        ->assertJsonPath('data.summary.explorationInterested', 1)
        ->assertJsonPath('data.summary.explorationInterestedRate', 100)
        ->assertJsonPath('data.summary.attributionMode', 'same-user-subject-impression-v1');
});

test('recommendation inspector exposes scoring components and exploration state', function () {
    $user = User::factory()->create(['city' => 'دمشق']);
    $category = Category::factory()->create(['status' => 'active']);
    UserPreference::query()->create([
        'user_id' => $user->id,
        'intent' => 'both',
        'preferred_city' => 'دمشق',
        'onboarding_completed_at' => now(),
    ]);
    UserCategoryInterest::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'explicit_weight' => 10,
        'behavioral_weight' => 0,
    ]);
    Post::factory()->create([
        'status' => 'published',
        'category_id' => $category->id,
        'location' => 'دمشق',
        'published_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/admin/recommendations/users/{$user->id}/preview?limit=5")
        ->assertOk();

    expect($response->json('data.recommendations.0.components.explicit_interest'))->toBe(30);
    expect($response->json('data.recommendations.0.components.same_city'))->toBe(25);
    expect($response->json('data.recommendations.0.isExploration'))->toBeFalse();
});

test('admin recommendation weight editing endpoints are removed', function () {
    $this->getJson('/api/v1/admin/recommendations/config')->assertNotFound();
    $this->patchJson('/api/v1/admin/recommendations/config', [
        'weights' => ['same_city' => 999],
    ])->assertNotFound();
    $this->deleteJson('/api/v1/admin/recommendations/config')->assertNotFound();

    expect(config('recommendations.weights.same_city'))->toBe(25);
});
