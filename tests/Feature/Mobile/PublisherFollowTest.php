<?php

declare(strict_types=1);

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Models\PublisherFollow;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can follow users and organizations idempotently and unfollow idempotently', function () {
    $actor = User::factory()->create(['status' => 'active']);
    $targetUser = User::factory()->create(['status' => 'active']);
    $organization = Organization::factory()->create(['status' => 'active']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/mobile/publishers/user/{$targetUser->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.isFollowing', true)
        ->assertJsonPath('data.followersCount', 1);

    $this->putJson("/api/mobile/publishers/user/{$targetUser->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.followersCount', 1);

    $this->putJson("/api/mobile/publishers/organization/{$organization->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.isFollowing', true);

    expect(PublisherFollow::query()->where('follower_user_id', $actor->id)->count())->toBe(2);

    $this->deleteJson("/api/mobile/publishers/user/{$targetUser->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.isFollowing', false)
        ->assertJsonPath('data.followersCount', 0);

    $this->deleteJson("/api/mobile/publishers/user/{$targetUser->id}/follow")
        ->assertOk()
        ->assertJsonPath('data.followersCount', 0);
});

test('self follow is rejected and invalid target type is validated', function () {
    $actor = User::factory()->create(['status' => 'active']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/mobile/publishers/user/{$actor->id}/follow")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['targetId'], 'error.details');

    $this->putJson("/api/mobile/publishers/company/{$actor->id}/follow")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['targetType'], 'error.details');
});

test('my following supports target type filters and publisher follow state', function () {
    $actor = User::factory()->create(['status' => 'active']);
    $targetUser = User::factory()->create(['status' => 'active']);
    $organization = Organization::factory()->create(['status' => 'active']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/mobile/publishers/user/{$targetUser->id}/follow")->assertOk();
    $this->putJson("/api/mobile/publishers/organization/{$organization->id}/follow")->assertOk();

    $this->getJson('/api/mobile/me/following?type=all&perPage=20')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/mobile/me/following?type=user&perPage=20')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.publisherType', 'user')
        ->assertJsonPath('data.0.isFollowing', true);

    $this->getJson('/api/mobile/me/following?type=organization&perPage=20')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.publisherType', 'organization');
});

test('publisher profile exposes followers count and viewer follow state', function () {
    $actor = User::factory()->create(['status' => 'active']);
    $organization = Organization::factory()->create(['status' => 'active']);

    Sanctum::actingAs($actor);
    $this->putJson("/api/mobile/publishers/organization/{$organization->id}/follow")->assertOk();

    $this->getJson("/api/mobile/discovery/publishers/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('data.followersCount', 1)
        ->assertJsonPath('data.isFollowing', true);
});

test('following feed includes eligible followed content and excludes non followed publishers', function () {
    $actor = User::factory()->create(['status' => 'active']);
    $followed = Organization::factory()->create(['status' => 'active']);
    $other = Organization::factory()->create(['status' => 'active']);

    $followedPost = Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Followed post',
        'type' => 'general',
        'status' => 'published',
        'organization_id' => $followed->id,
        'published_at' => now(),
    ]);
    Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Hidden other post',
        'type' => 'general',
        'status' => 'published',
        'organization_id' => $other->id,
        'published_at' => now()->subMinute(),
    ]);
    Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Followed campaign',
        'status' => 'active',
        'organization_id' => $followed->id,
    ]);
    Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Followed draft',
        'type' => 'general',
        'status' => 'draft',
        'organization_id' => $followed->id,
    ]);

    Sanctum::actingAs($actor);
    $this->putJson("/api/mobile/publishers/organization/{$followed->id}/follow")->assertOk();

    $response = $this->getJson('/api/mobile/discovery/following?perPage=20');
    $response->assertOk()->assertJsonPath('meta.total', 2);
    expect(collect($response->json('data'))->pluck('content.id'))->toContain($followedPost->id);
    $response->assertJsonMissing(['title' => 'Hidden other post']);
    $response->assertJsonMissing(['title' => 'Followed draft']);
});

test('publisher follower notification fanout honors unfollow', function () {
    $publisher = User::factory()->create(['status' => 'active']);
    $follower = User::factory()->create(['status' => 'active']);

    PublisherFollow::query()->create([
        'id' => (string) Str::uuid(),
        'follower_user_id' => $follower->id,
        'target_type' => 'user',
        'target_id' => $publisher->id,
        'notification_level' => 'all',
    ]);

    $notifications = app(NotificationEventService::class);
    expect($notifications->notifyPublisherFollowers(
        'user',
        (string) $publisher->id,
        NotificationEventType::PostPublished,
        'New post',
        'A followed publisher posted.',
        'post',
        'normal',
        'Post',
        '/posts/example',
        null,
        (string) $publisher->id,
    ))->toBe(1);

    expect($follower->notifications()->count())->toBe(1);

    PublisherFollow::query()->where('follower_user_id', $follower->id)->delete();

    expect($notifications->notifyPublisherFollowers(
        'user',
        (string) $publisher->id,
        NotificationEventType::PostPublished,
        'Second post',
        'Another post.',
        'post',
    ))->toBe(0);
    expect($follower->notifications()->count())->toBe(1);
});
