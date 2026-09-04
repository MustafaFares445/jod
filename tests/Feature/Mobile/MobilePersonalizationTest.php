<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Capability;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MobilePersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_options_expose_only_active_personalization_choices(): void
    {
        $category = Category::factory()->create(['name' => 'التعليم', 'status' => 'active']);
        Category::factory()->create(['name' => 'مخفي', 'status' => 'inactive']);
        $capability = Capability::query()->create(['name' => 'تدريس', 'slug' => 'teaching', 'status' => 'active', 'sort_order' => 1]);

        $response = $this->getJson('/api/mobile/onboarding/options');

        $response->assertOk()
            ->assertJsonPath('data.categories.0.id', $category->id)
            ->assertJsonPath('data.capabilities.0.id', $capability->id)
            ->assertJsonFragment(['value' => 'giver'])
            ->assertJsonFragment(['value' => 'receiver'])
            ->assertJsonMissing(['name' => 'مخفي']);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('onboarding', $data);
        $this->assertArrayNotHasKey('availabilityStatuses', $data);
    }

    public function test_complete_personalization_returns_simplified_profile(): void
    {
        $user = User::factory()->create(['city' => null]);
        $category = Category::factory()->create(['status' => 'active']);
        $capability = Capability::query()->create(['name' => 'نقل', 'slug' => 'transport', 'status' => 'active', 'sort_order' => 1]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/mobile/me/onboarding', [
            'intent' => 'giver',
            'categoryIds' => [$category->id],
            'capabilityIds' => [$capability->id],
            'preferredCity' => 'دمشق',
            'remoteHelpEnabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.onboardingCompleted', true)
            ->assertJsonPath('data.missingFields', [])
            ->assertJsonPath('data.intent', 'giver')
            ->assertJsonPath('data.preferredCity', 'دمشق')
            ->assertJsonPath('data.remoteHelpEnabled', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        foreach (['onboardingCompletedAt', 'onboardingProfileComplete', 'onboardingNeedsCompletion', 'onboardingMissingFields', 'preferredGovernorate', 'preferredRadiusKm', 'availabilityStatus'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
    }

    public function test_empty_onboarding_payload_finishes_flow_without_answers(): void
    {
        $user = User::factory()->create(['city' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/me/onboarding', [])
            ->assertOk()
            ->assertJsonPath('data.onboardingCompleted', true)
            ->assertJsonPath('data.missingFields', ['intent', 'interests', 'preferredCity']);
    }

    public function test_empty_onboarding_payload_preserves_existing_answers(): void
    {
        $user = User::factory()->create(['city' => 'دمشق']);
        $category = Category::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/me/onboarding', ['intent' => 'receiver', 'categoryIds' => [$category->id]])->assertOk();

        $this->postJson('/api/mobile/me/onboarding', [])
            ->assertOk()
            ->assertJsonPath('data.intent', 'receiver')
            ->assertJsonPath('data.interests.0.category.id', $category->id)
            ->assertJsonPath('data.preferredCity', 'دمشق')
            ->assertJsonPath('data.missingFields', []);
    }

    public function test_giver_requires_capabilities_but_receiver_does_not(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $capability = Capability::query()->create(['name' => 'نقل', 'slug' => 'transport', 'status' => 'active', 'sort_order' => 1]);

        $giver = User::factory()->create(['city' => 'دمشق']);
        Sanctum::actingAs($giver);
        $this->postJson('/api/mobile/me/onboarding', ['intent' => 'giver', 'categoryIds' => [$category->id]])
            ->assertOk()->assertJsonPath('data.missingFields', ['capabilities']);
        $this->patchJson('/api/mobile/me/capabilities', ['capabilityIds' => [$capability->id]])
            ->assertOk()->assertJsonPath('data.missingFields', []);

        $receiver = User::factory()->create(['city' => 'حلب']);
        Sanctum::actingAs($receiver);
        $this->postJson('/api/mobile/me/onboarding', ['intent' => 'receiver', 'categoryIds' => [$category->id]])
            ->assertOk()->assertJsonPath('data.missingFields', []);
    }

    public function test_user_can_replace_interests_and_update_active_preferences(): void
    {
        $user = User::factory()->create(['city' => null]);
        $first = Category::factory()->create(['status' => 'active']);
        $second = Category::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/me/onboarding', ['intent' => 'receiver', 'categoryIds' => [$first->id], 'preferredCity' => 'دمشق'])->assertOk();
        $this->patchJson('/api/mobile/me/interests', ['categoryIds' => [$second->id]])
            ->assertOk()->assertJsonPath('data.interests.0.category.id', $second->id);
        $this->patchJson('/api/mobile/me/preferences', ['preferredCity' => 'حلب', 'remoteHelpEnabled' => true])
            ->assertOk()
            ->assertJsonPath('data.preferredCity', 'حلب')
            ->assertJsonPath('data.remoteHelpEnabled', true)
            ->assertJsonPath('data.missingFields', []);
    }

    public function test_personalization_endpoints_require_authentication(): void
    {
        $this->getJson('/api/mobile/me/preferences')->assertUnauthorized();
        $this->postJson('/api/mobile/me/onboarding', [])->assertUnauthorized();
    }
}
