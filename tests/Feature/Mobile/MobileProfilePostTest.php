<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileProfilePostTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_feed_returns_only_statuses_the_profile_screen_can_represent(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
            'email' => 'profile@example.test',
            'city' => 'Damascus',
        ]);
        $published = Post::factory()->published()->create([
            'author_id' => $user->id,
            'organization_id' => null,
            'type' => 'help_request',
            'title' => 'Published profile post',
            'content' => 'Published details',
            'location' => 'Aleppo',
        ]);
        $rejected = Post::factory()->create([
            'author_id' => $user->id,
            'organization_id' => null,
            'type' => 'help_request',
            'status' => 'rejected',
            'title' => 'Rejected profile post',
            'content' => 'Rejected details',
            'location' => 'Homs',
            'rejection_reason' => 'Needs more context',
        ]);
        $archived = Post::factory()->create([
            'author_id' => $user->id,
            'organization_id' => null,
            'type' => 'help_request',
            'status' => 'archived',
            'title' => 'Archived profile post',
        ]);
        Post::factory()->create(['author_id' => $user->id, 'status' => 'draft', 'title' => 'Draft workflow post']);
        Post::factory()->create(['author_id' => $user->id, 'status' => 'pending', 'title' => 'Pending workflow post']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/me/profile-posts?perPage=10');
        $response->assertOk()
            ->assertJsonPath('meta.total', 3);

        $items = collect($response->json('data'))->keyBy('id');
        $this->assertSame('posted', $items[$published->id]['profileStatus']);
        $this->assertSame('unposted', $items[$rejected->id]['profileStatus']);
        $this->assertSame('archived', $items[$archived->id]['profileStatus']);
        $this->assertSame('Needs more context', $items[$rejected->id]['rejectionReason']);
        $this->assertSame('Aleppo', $items[$published->id]['publisher']['city']);
        $this->assertArrayHasKey('stats', $items[$published->id]);
        $this->assertArrayHasKey('cta', $items[$published->id]);
        $this->assertArrayHasKey('images', $items[$published->id]);

        $response->assertJsonMissing(['title' => 'Draft workflow post']);
        $response->assertJsonMissing(['title' => 'Pending workflow post']);
    }

    public function test_profile_feed_status_filter_uses_profile_vocabulary(): void
    {
        $user = User::factory()->create();
        $rejected = Post::factory()->create([
            'author_id' => $user->id,
            'status' => 'rejected',
            'title' => 'Rejected only',
        ]);
        Post::factory()->published()->create([
            'author_id' => $user->id,
            'title' => 'Published hidden by filter',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/profile-posts?status=unposted')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rejected->id)
            ->assertJsonPath('data.0.profileStatus', 'unposted');
    }

    public function test_profile_feed_is_scoped_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = Post::factory()->published()->create(['author_id' => $user->id]);
        Post::factory()->published()->create(['author_id' => $other->id, 'title' => 'Other user post']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/me/profile-posts?perPage=10');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $own->id);
        $response->assertJsonMissing(['title' => 'Other user post']);
    }

    public function test_profile_feed_requires_authentication(): void
    {
        $this->getJson('/api/mobile/me/profile-posts')->assertUnauthorized();
    }
}
