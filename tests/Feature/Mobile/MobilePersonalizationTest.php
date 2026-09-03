<?php

declare(strict_types=1);

use App\Models\Capability;
use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('onboarding options expose active categories capabilities and preference enums', function () {
    $category = Category::factory()->create(['name' => 'التعليم', 'status' => 'active']);
    Category::factory()->create(['name' => 'مخفي', 'status' => 'inactive']);
    $capability = Capability::query()->create([
        'name' => 'تدريس',
        'slug' => 'teaching',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $response = $this->getJson('/api/mobile/onboarding/options');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.categories.0.id', $category->id)
        ->assertJsonPath('data.capabilities.0.id', $capability->id)
        ->assertJsonFragment(['value' => 'giver'])
        ->assertJsonFragment(['value' => 'receiver'])
        ->assertJsonMissing(['name' => 'مخفي']);
});

test('authenticated user can complete personalization onboarding', function () {
    $user = User::factory()->create(['city' => 'دمشق']);
    $category = Category::factory()->create(['status' => 'active']);
    $capability = Capability::query()->create([
        'name' => 'نقل',
        'slug' => 'transport',
        'status' => 'active',
        'sort_order' => 1,
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/mobile/me/onboarding', [
        'intent' => 'giver',
        'categoryIds' => [$category->id],
        'capabilityIds' => [$capability->id],
        'preferredCity' => 'دمشق',
        'remoteHelpEnabled' => true,
        'availabilityStatus' => 'weekends',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.onboardingCompleted', true)
        ->assertJsonPath('data.intent', 'giver')
        ->assertJsonPath('data.preferredCity', 'دمشق')
        ->assertJsonPath('data.interests.0.category.id', $category->id)
        ->assertJsonPath('data.capabilities.0.id', $capability->id);

    $this->assertDatabaseHas('user_preferences', [
        'user_id' => $user->id,
        'intent' => 'giver',
        'preferred_city' => 'دمشق',
        'remote_help_enabled' => 1,
    ]);
    $this->assertDatabaseHas('user_category_interests', [
        'user_id' => $user->id,
        'category_id' => $category->id,
        'explicit_weight' => 10,
    ]);
    $this->assertDatabaseHas('user_capabilities', [
        'user_id' => $user->id,
        'capability_id' => $capability->id,
    ]);
});

test('user can replace interests and update preferences', function () {
    $user = User::factory()->create();
    $first = Category::factory()->create(['status' => 'active']);
    $second = Category::factory()->create(['status' => 'active']);
    Sanctum::actingAs($user);

    $this->postJson('/api/mobile/me/onboarding', [
        'intent' => 'both',
        'categoryIds' => [$first->id],
    ])->assertOk();

    $this->patchJson('/api/mobile/me/interests', [
        'categoryIds' => [$second->id],
    ])->assertOk()
        ->assertJsonPath('data.interests.0.category.id', $second->id);

    $this->patchJson('/api/mobile/me/preferences', [
        'intent' => 'receiver',
        'preferredCity' => 'حلب',
        'availabilityStatus' => 'evenings',
    ])->assertOk()
        ->assertJsonPath('data.intent', 'receiver')
        ->assertJsonPath('data.preferredCity', 'حلب')
        ->assertJsonPath('data.availabilityStatus', 'evenings');

    $this->assertDatabaseMissing('user_category_interests', [
        'user_id' => $user->id,
        'category_id' => $first->id,
        'explicit_weight' => 10,
    ]);
});

test('personalization endpoints require authentication', function () {
    $this->getJson('/api/mobile/me/preferences')->assertUnauthorized();
    $this->postJson('/api/mobile/me/onboarding', [])->assertUnauthorized();
});
