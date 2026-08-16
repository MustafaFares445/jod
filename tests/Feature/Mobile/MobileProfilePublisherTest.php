<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Donation;
use App\Models\Organization;
use App\Models\Post;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileProfilePublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_returns_screen_fields_and_summary_counts(): void
    {
        $user = User::factory()->create([
            'name' => 'Jawad User',
            'email' => 'jawad.user@jod.org',
            'city' => 'Damascus',
            'bio' => 'Community volunteer',
        ]);

        Post::factory()->count(2)->published()->create(['author_id' => $user->id]);
        $savedPost = Post::factory()->published()->create();
        SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $savedPost->id]);
        Donation::factory()->create(['created_by' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Jawad User')
            ->assertJsonPath('data.username', 'jawad.user')
            ->assertJsonPath('data.city', 'Damascus')
            ->assertJsonPath('data.bio', 'Community volunteer')
            ->assertJsonPath('data.stats.postsCount', 2)
            ->assertJsonPath('data.stats.savedCount', 1)
            ->assertJsonPath('data.stats.donationsCount', 1);
    }

    public function test_profile_update_persists_city_and_bio_and_can_clear_nullable_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'phone' => '0999999999',
            'city' => 'Old City',
            'bio' => 'Old bio',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/mobile/me/profile', [
            'name' => 'New Name',
            'email' => 'new@example.test',
            'phone' => null,
            'city' => 'Aleppo',
            'bio' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.city', 'Aleppo')
            ->assertJsonPath('data.bio', null)
            ->assertJsonPath('data.phone', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.test',
            'phone' => null,
            'city' => 'Aleppo',
            'bio' => null,
        ]);
    }

    public function test_organization_publisher_returns_organization_identity_and_only_organization_posts(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'JOD Charity',
            'email' => 'hello@jod.org',
            'location' => 'Damascus',
            'description' => 'Verified humanitarian organization',
            'verification_status' => 'verified',
        ]);
        $author = User::factory()->create(['city' => 'Homs']);

        $organizationPost = Post::factory()->published()->create([
            'organization_id' => $organization->id,
            'author_id' => $author->id,
            'title' => 'Organization post',
        ]);
        Post::factory()->published()->create([
            'author_id' => $author->id,
            'organization_id' => null,
            'title' => 'Personal post',
        ]);
        Post::factory()->create([
            'organization_id' => $organization->id,
            'author_id' => $author->id,
            'title' => 'Draft organization post',
        ]);

        $this->getJson("/api/mobile/discovery/publishers/{$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.name', 'JOD Charity')
            ->assertJsonPath('data.username', 'hello')
            ->assertJsonPath('data.city', 'Damascus')
            ->assertJsonPath('data.bio', 'Verified humanitarian organization')
            ->assertJsonPath('data.verified', true);

        $response = $this->getJson("/api/mobile/discovery/publishers/{$organization->id}/posts?perPage=10");
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $organizationPost->id)
            ->assertJsonPath('data.0.publisher.id', $organization->id);
        $response->assertJsonMissing(['title' => 'Personal post']);
        $response->assertJsonMissing(['title' => 'Draft organization post']);
    }

    public function test_individual_publisher_uses_user_profile_and_excludes_organization_backed_posts(): void
    {
        $user = User::factory()->create([
            'name' => 'Individual Publisher',
            'email' => 'publisher@example.test',
            'city' => 'Latakia',
            'bio' => 'Independent volunteer',
        ]);
        $organization = Organization::factory()->create();

        $personalPost = Post::factory()->published()->create([
            'author_id' => $user->id,
            'organization_id' => null,
            'title' => 'Personal public post',
        ]);
        Post::factory()->published()->create([
            'author_id' => $user->id,
            'organization_id' => $organization->id,
            'title' => 'Organization-backed post',
        ]);

        $this->getJson("/api/mobile/discovery/publishers/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Individual Publisher')
            ->assertJsonPath('data.username', 'publisher')
            ->assertJsonPath('data.city', 'Latakia')
            ->assertJsonPath('data.bio', 'Independent volunteer');

        $response = $this->getJson("/api/mobile/discovery/publishers/{$user->id}/posts?perPage=10");
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $personalPost->id)
            ->assertJsonPath('data.0.publisher.id', $user->id)
            ->assertJsonPath('data.0.publisher.city', 'Latakia')
            ->assertJsonPath('data.0.publisher.bio', 'Independent volunteer');
        $response->assertJsonMissing(['title' => 'Organization-backed post']);
    }

    public function test_inactive_publishers_are_not_public(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->getJson("/api/mobile/discovery/publishers/{$user->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }
}
