<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_discovery_posts_returns_only_published_posts(): void
    {
        $organization = Organization::factory()->create();
        $publishedPost = Post::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Published post',
            'summary' => 'Visible summary',
            'type' => 'general',
            'status' => 'published',
            'location' => 'Amman',
            'organization_id' => $organization->id,
            'views_count' => 5,
            'reactions_count' => 2,
            'applications_count' => 1,
            'published_at' => now(),
        ]);
        Post::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Draft post',
            'summary' => 'Hidden summary',
            'type' => 'general',
            'status' => 'draft',
            'location' => 'Irbid',
            'organization_id' => $organization->id,
        ]);

        $response = $this->getJson('/api/mobile/discovery/posts?perPage=10');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $publishedPost->id);
        $response->assertJsonMissing(['title' => 'Draft post']);
        $response->assertJsonPath('meta.currentPage', 1);
        $response->assertJsonPath('meta.perPage', 10);
    }

    public function test_mobile_discovery_posts_include_viewer_state_when_authenticated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/discovery/posts');

        $response->assertOk();
        $response->assertJsonPath('meta.viewer.isAuthenticated', true);
        $response->assertJsonPath('meta.viewer.userId', $user->id);
    }

    public function test_mobile_discovery_post_show_returns_404_for_unpublished_content(): void
    {
        $post = Post::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Hidden post',
            'status' => 'draft',
        ]);

        $response = $this->getJson("/api/mobile/discovery/posts/{$post->id}");

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'not_found');
    }

    public function test_mobile_discovery_campaigns_return_only_active_campaigns(): void
    {
        $organization = Organization::factory()->create();
        $activeCampaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Active campaign',
            'summary' => 'Visible campaign',
            'category' => 'health',
            'status' => 'active',
            'location' => 'Zarqa',
            'organization_id' => $organization->id,
            'goal_amount' => 1000,
            'raised_amount' => 250,
        ]);
        Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Closed campaign',
            'status' => 'closed',
            'category' => 'health',
            'organization_id' => $organization->id,
        ]);

        $response = $this->getJson('/api/mobile/discovery/campaigns?perPage=10');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $activeCampaign->id);
        $response->assertJsonMissing(['title' => 'Closed campaign']);
    }

    public function test_mobile_discovery_campaign_show_returns_404_for_inactive_content(): void
    {
        $campaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Inactive campaign',
            'status' => 'draft',
            'category' => 'health',
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $response = $this->getJson("/api/mobile/discovery/campaigns/{$campaign->id}");

        $response->assertNotFound();
        $response->assertJsonPath('error.code', 'not_found');
    }

    public function test_mobile_discovery_categories_return_active_categories_and_pagination_meta(): void
    {
        Category::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Food support',
            'target' => 'post',
            'description' => 'Food related posts',
            'status' => 'active',
            'usage_count' => 3,
        ]);
        Category::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Archived category',
            'target' => 'campaign',
            'description' => 'Hidden from discovery',
            'status' => 'inactive',
            'usage_count' => 1,
        ]);

        $response = $this->getJson('/api/mobile/discovery/categories?perPage=10');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.name', 'Food support');
        $response->assertJsonMissing(['name' => 'Archived category']);
        $response->assertJsonPath('meta.currentPage', 1);
        $response->assertJsonPath('meta.perPage', 10);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('meta.lastPage', 1);
    }

    public function test_mobile_discovery_empty_state_returns_valid_pagination_metadata(): void
    {
        $response = $this->getJson('/api/mobile/discovery/posts?perPage=5');

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.currentPage', 1);
        $response->assertJsonPath('meta.perPage', 5);
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('meta.lastPage', 1);
    }
}
