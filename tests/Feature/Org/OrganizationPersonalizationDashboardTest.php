<?php

declare(strict_types=1);

use App\Enums\PersonalizationEventType;
use App\Models\Capability;
use App\Models\Category;
use App\Models\HelpOffer;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\Post;
use App\Models\RecommendationImpression;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    [$this->organization, $this->owner] = org_personalization_owner();
    Sanctum::actingAs($this->owner, ['access']);
});

test('organization creates categorized service content', function () {
    $category = Category::factory()->create(['status' => 'active']);

    $response = $this->postJson('/api/v1/org/posts', [
        'title' => 'استشارات تعليمية',
        'summary' => 'جلسات دعم مجانية للطلاب',
        'type' => 'service_offer',
        'categoryId' => $category->id,
        'location' => 'دمشق',
        'status' => 'published',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.type', 'service_offer')
        ->assertJsonPath('data.categoryId', (string) $category->id);

    $this->assertDatabaseHas('posts', [
        'organization_id' => $this->organization->id,
        'category_id' => $category->id,
        'type' => 'service_offer',
    ]);
});

test('organization post category is required and must be active', function () {
    $inactive = Category::factory()->create(['status' => 'inactive']);

    $this->postJson('/api/v1/org/posts', [
        'title' => 'بدون تصنيف',
        'summary' => 'محتوى بلا تصنيف',
        'type' => 'general',
        'location' => 'دمشق',
    ])->assertUnprocessable()->assertJsonValidationErrors('categoryId');

    $this->postJson('/api/v1/org/posts', [
        'title' => 'تصنيف غير فعال',
        'summary' => 'محتوى بتصنيف غير فعال',
        'type' => 'general',
        'categoryId' => $inactive->id,
        'location' => 'دمشق',
    ])->assertUnprocessable()->assertJsonValidationErrors('categoryId');
});

test('organization help request supports urgency expiration and required capabilities', function () {
    $category = Category::factory()->create(['status' => 'active']);
    $capability = Capability::query()->create([
        'name' => 'النقل', 'slug' => 'transport', 'status' => 'active', 'sort_order' => 1,
    ]);

    $response = $this->postJson('/api/v1/org/posts', [
        'title' => 'مطلوب نقل مريض',
        'summary' => 'نحتاج وسيلة نقل للمستشفى',
        'type' => 'help_request',
        'categoryId' => $category->id,
        'location' => 'دمشق',
        'urgency' => 'urgent',
        'urgencyReason' => 'الموعد الطبي خلال ساعات',
        'expiresAt' => now()->addDay()->toIso8601String(),
        'requiredCapabilityIds' => [$capability->id],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.helpStatus', 'open')
        ->assertJsonPath('data.urgency', 'urgent')
        ->assertJsonPath('data.requiredCapabilities.0.slug', 'transport');

    $postId = $response->json('data.id');
    $this->assertDatabaseHas('post_capabilities', ['post_id' => $postId, 'capability_id' => $capability->id]);
});

test('organization cannot mark its own help request critical', function () {
    $category = Category::factory()->create(['status' => 'active']);

    $this->postJson('/api/v1/org/posts', [
        'title' => 'طلب حرج',
        'summary' => 'وصف الطلب الحرج',
        'type' => 'help_request',
        'categoryId' => $category->id,
        'location' => 'دمشق',
        'urgency' => 'critical',
        'urgencyReason' => 'سبب كافٍ للتوضيح',
    ])->assertUnprocessable()->assertJsonValidationErrors('urgency');
});

test('organization urgent help request requires an urgency reason', function () {
    $category = Category::factory()->create(['status' => 'active']);

    $this->postJson('/api/v1/org/posts', [
        'title' => 'طلب عاجل',
        'summary' => 'وصف الطلب العاجل',
        'type' => 'help_request',
        'categoryId' => $category->id,
        'location' => 'دمشق',
        'urgency' => 'urgent',
    ])->assertUnprocessable()->assertJsonValidationErrors('urgencyReason');
});

test('organization help request listing is isolated from other organizations', function () {
    $other = Organization::factory()->create(['status' => 'active', 'verification_status' => 'verified']);
    $mine = Post::factory()->create(['organization_id' => $this->organization->id, 'author_id' => $this->owner->id, 'type' => 'help_request', 'status' => 'published', 'help_status' => 'open']);
    $foreign = Post::factory()->create(['organization_id' => $other->id, 'type' => 'help_request', 'status' => 'published', 'help_status' => 'open']);

    $this->getJson('/api/v1/org/help-requests')
        ->assertOk()
        ->assertJsonFragment(['id' => (string) $mine->id])
        ->assertJsonMissing(['id' => (string) $foreign->id]);

    $this->getJson("/api/v1/org/help-requests/{$foreign->id}")->assertNotFound();
});

test('request scoped help offers never leak offers from another request', function () {
    $mine = Post::factory()->create([
        'organization_id' => $this->organization->id,
        'author_id' => $this->owner->id,
        'type' => 'help_request',
        'status' => 'published',
        'help_status' => 'open',
    ]);
    $otherRequest = Post::factory()->create([
        'organization_id' => $this->organization->id,
        'author_id' => $this->owner->id,
        'type' => 'help_request',
        'status' => 'published',
        'help_status' => 'open',
    ]);
    $helper = User::factory()->create();
    $otherHelper = User::factory()->create();

    $mineOffer = HelpOffer::query()->create([
        'post_id' => $mine->id,
        'helper_user_id' => $helper->id,
        'post_owner_id' => $this->owner->id,
        'type' => 'transport',
        'status' => 'pending',
    ]);
    $otherOffer = HelpOffer::query()->create([
        'post_id' => $otherRequest->id,
        'helper_user_id' => $otherHelper->id,
        'post_owner_id' => $this->owner->id,
        'type' => 'teaching',
        'status' => 'pending',
    ]);

    $this->getJson("/api/v1/org/help-requests/{$mine->id}/offers")
        ->assertOk()
        ->assertJsonFragment(['id' => (string) $mineOffer->id])
        ->assertJsonMissing(['id' => (string) $otherOffer->id]);
});

test('organization cannot inspect another organizations help offer', function () {
    [$otherOrganization, $otherOwner] = org_personalization_owner();
    $helper = User::factory()->create();
    $foreignPost = Post::factory()->create([
        'organization_id' => $otherOrganization->id,
        'author_id' => $otherOwner->id,
        'type' => 'help_request',
        'status' => 'published',
        'help_status' => 'open',
    ]);
    $offer = HelpOffer::query()->create([
        'post_id' => $foreignPost->id,
        'helper_user_id' => $helper->id,
        'post_owner_id' => $otherOwner->id,
        'type' => 'other',
        'status' => 'pending',
    ]);

    $this->getJson("/api/v1/org/help-offers/{$offer->id}")->assertNotFound();
});

test('organization can close its help request with an outcome', function () {
    $post = Post::factory()->create(['organization_id' => $this->organization->id, 'author_id' => $this->owner->id, 'type' => 'help_request', 'status' => 'published', 'help_status' => 'open']);

    $this->patchJson("/api/v1/org/help-requests/{$post->id}/status", ['status' => 'fulfilled'])
        ->assertOk()->assertJsonPath('data.helpStatus', 'fulfilled');

    expect($post->refresh()->fulfilled_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'post', 'entity_id' => $post->id, 'action' => 'help_request.status_changed']);
});

test('organization cannot manually set expired lifecycle state', function () {
    $post = Post::factory()->create(['organization_id' => $this->organization->id, 'author_id' => $this->owner->id, 'type' => 'help_request', 'status' => 'published', 'help_status' => 'open']);

    $this->patchJson("/api/v1/org/help-requests/{$post->id}/status", ['status' => 'expired'])
        ->assertUnprocessable();
});

test('organization capability brief returns active options only', function () {
    Capability::query()->create(['name' => 'التدريس', 'slug' => 'teaching', 'status' => 'active', 'sort_order' => 1]);
    Capability::query()->create(['name' => 'قديم', 'slug' => 'legacy', 'status' => 'inactive', 'sort_order' => 2]);

    $this->getJson('/api/v1/org/capabilities/brief')
        ->assertOk()->assertJsonFragment(['slug' => 'teaching'])->assertJsonMissing(['slug' => 'legacy']);
});

test('organization recommendation analytics never includes another publisher', function () {
    $other = Organization::factory()->create(['status' => 'active', 'verification_status' => 'verified']);
    $viewer = User::factory()->create();
    $mine = Post::factory()->create(['organization_id' => $this->organization->id, 'status' => 'published']);
    $foreign = Post::factory()->create(['organization_id' => $other->id, 'status' => 'published']);

    RecommendationImpression::query()->create([
        'user_id' => $viewer->id, 'subject_type' => 'post', 'subject_id' => $mine->id,
        'feed_type' => 'for_you', 'publisher_type' => 'organization', 'publisher_id' => $this->organization->id,
        'score' => 60, 'reasons' => ['explicit_interest'], 'shown_at' => now()->subMinute(),
    ]);
    RecommendationImpression::query()->create([
        'user_id' => $viewer->id, 'subject_type' => 'post', 'subject_id' => $foreign->id,
        'feed_type' => 'for_you', 'publisher_type' => 'organization', 'publisher_id' => $other->id,
        'score' => 60, 'reasons' => ['explicit_interest'], 'shown_at' => now()->subMinute(),
    ]);
    UserInteraction::query()->create([
        'user_id' => $viewer->id, 'event_type' => PersonalizationEventType::PostOpen,
        'subject_type' => 'post', 'subject_id' => $mine->id, 'publisher_id' => $this->organization->id, 'occurred_at' => now(),
    ]);

    $this->getJson('/api/v1/org/analytics/recommendations')
        ->assertOk()
        ->assertJsonPath('data.summary.impressions', 1)
        ->assertJsonPath('data.summary.opens', 1);
});

test('organization analytics post type filter scopes impressions', function () {
    $viewer = User::factory()->create();
    $help = Post::factory()->create(['organization_id' => $this->organization->id, 'type' => 'help_request', 'status' => 'published']);
    $service = Post::factory()->create(['organization_id' => $this->organization->id, 'type' => 'service_offer', 'status' => 'published']);

    foreach ([$help, $service] as $post) {
        RecommendationImpression::query()->create([
            'user_id' => $viewer->id,
            'subject_type' => 'post',
            'subject_id' => $post->id,
            'feed_type' => 'for_you',
            'publisher_type' => 'organization',
            'publisher_id' => $this->organization->id,
            'score' => 50,
            'reasons' => ['explicit_interest'],
            'shown_at' => now(),
        ]);
    }

    $this->getJson('/api/v1/org/analytics/recommendations?contentType=post&postType=help_request')
        ->assertOk()
        ->assertJsonPath('data.summary.impressions', 1);
});

test('organization new follower metric requires prior recommendation exposure', function () {
    $attributed = User::factory()->create();
    $organic = User::factory()->create();
    $post = Post::factory()->create(['organization_id' => $this->organization->id, 'status' => 'published']);

    RecommendationImpression::query()->create([
        'user_id' => $attributed->id,
        'subject_type' => 'post',
        'subject_id' => $post->id,
        'feed_type' => 'for_you',
        'publisher_type' => 'organization',
        'publisher_id' => $this->organization->id,
        'score' => 50,
        'reasons' => ['explicit_interest'],
        'shown_at' => now()->subMinute(),
    ]);

    foreach ([$attributed, $organic] as $viewer) {
        UserInteraction::query()->create([
            'user_id' => $viewer->id,
            'event_type' => PersonalizationEventType::PublisherFollow,
            'subject_type' => 'publisher',
            'subject_id' => $this->organization->id,
            'publisher_id' => $this->organization->id,
            'occurred_at' => now(),
        ]);
    }

    $this->getJson('/api/v1/org/analytics/recommendations')
        ->assertOk()
        ->assertJsonPath('data.summary.newFollowers', 1);
});

/** @return array{Organization, User} */
function org_personalization_owner(): array
{
    $organization = Organization::factory()->create(['status' => 'active', 'verification_status' => 'verified']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $role = OrganizationRole::factory()->create(['organization_id' => $organization->id, 'is_active' => true, 'is_system' => true]);
    OrganizationStaff::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'organization_role_id' => $role->id,
        'status' => 'active',
    ]);
    return [$organization, $user];
}
