<?php

declare(strict_types=1);
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Post;
use App\Models\SavedPost;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('mobile discovery posts returns only published posts', function () {
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
});
test('mobile discovery posts return canonical home post contract', function () {
    $organization = Organization::factory()->create([
        'name' => 'JOD Relief',
        'email' => 'relief@jod.test',
        'phone' => '0999999999',
        'location' => 'Damascus',
        'description' => 'Community relief organization.',
        'verification_status' => 'verified',
    ]);
    $campaign = Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Food campaign',
        'category' => 'food',
        'status' => 'active',
        'organization_id' => $organization->id,
    ]);
    $post = Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Support the food campaign',
        'summary' => 'Short campaign summary',
        'content' => 'Full campaign details for mobile.',
        'type' => 'donation_campaign',
        'status' => 'published',
        'location' => 'Damascus',
        'organization_id' => $organization->id,
        'campaign_id' => $campaign->id,
        'reactions_count' => 12,
        'published_at' => now(),
    ]);

    $response = $this->getJson('/api/mobile/discovery/posts?perPage=10');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $post->id)
        ->assertJsonPath('data.0.publisher.id', $organization->id)
        ->assertJsonPath('data.0.publisher.name', 'JOD Relief')
        ->assertJsonPath('data.0.publisher.username', 'relief')
        ->assertJsonPath('data.0.publisher.city', 'Damascus')
        ->assertJsonPath('data.0.publisher.verified', true)
        ->assertJsonPath('data.0.publisher.phoneNumber', '0999999999')
        ->assertJsonPath('data.0.postType', 'donation_campaign')
        ->assertJsonPath('data.0.content', 'Full campaign details for mobile.')
        ->assertJsonPath('data.0.cta.type', 'donate')
        ->assertJsonPath('data.0.cta.state', 'open')
        ->assertJsonPath('data.0.cta.targetId', $campaign->id)
        ->assertJsonPath('data.0.stats.likes', 12)
        ->assertJsonPath('data.0.stats.comments', 0)
        ->assertJsonPath('data.0.stats.shares', 0)
        ->assertJsonPath('data.0.saved', false)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'publisher' => ['id', 'name', 'username', 'verified'],
                'postType',
                'title',
                'content',
                'createdAt',
                'images',
                'cta' => ['type', 'label', 'targetId', 'state'],
                'stats' => ['likes', 'comments', 'shares'],
                'saved',
            ]],
        ]);
});
test('mobile discovery post includes saved and submitted state for authenticated viewer', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $campaign = Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Volunteer campaign',
        'category' => 'health',
        'status' => 'active',
        'organization_id' => $organization->id,
    ]);
    $post = Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Volunteer with us',
        'content' => 'Volunteer campaign details.',
        'type' => 'volunteer_opportunity',
        'status' => 'published',
        'organization_id' => $organization->id,
        'campaign_id' => $campaign->id,
        'published_at' => now(),
    ]);
    SavedPost::factory()->create([
        'user_id' => $user->id,
        'post_id' => $post->id,
    ]);
    CampaignApplication::query()->create([
        'organization_id' => $organization->id,
        'campaign_id' => $campaign->id,
        'name' => $user->name,
        'email' => $user->email,
        'campaign_title' => $campaign->title,
        'applicant_status' => 'pending',
        'applied_at' => now(),
        'created_by' => $user->id,
    ]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->getJson("/api/mobile/discovery/posts/{$post->id}");

    $response->assertOk()
        ->assertJsonPath('data.saved', true)
        ->assertJsonPath('data.cta.type', 'apply')
        ->assertJsonPath('data.cta.state', 'submitted')
        ->assertJsonPath('data.cta.targetId', $campaign->id)
        ->assertJsonPath('meta.viewer.isAuthenticated', true)
        ->assertJsonPath('meta.viewer.userId', $user->id);
});
test('mobile discovery posts include viewer state when authenticated', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->getJson('/api/mobile/discovery/posts');

    $response->assertOk();
    $response->assertJsonPath('meta.viewer.isAuthenticated', true);
    $response->assertJsonPath('meta.viewer.userId', $user->id);
});
test('mobile discovery post show returns 404 for unpublished content', function () {
    $post = Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Hidden post',
        'status' => 'draft',
    ]);

    $response = $this->getJson("/api/mobile/discovery/posts/{$post->id}");

    $response->assertNotFound();
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('error.code', 'not_found');
});
test('mobile discovery campaigns return only active campaigns', function () {
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
});
test('mobile discovery campaign show returns 404 for inactive content', function () {
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
});
test('mobile discovery categories return active categories and pagination meta', function () {
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
});
test('mobile discovery empty state returns valid pagination metadata', function () {
    $response = $this->getJson('/api/mobile/discovery/posts?perPage=5');

    $response->assertOk();
    $response->assertJsonPath('data', []);
    $response->assertJsonPath('meta.currentPage', 1);
    $response->assertJsonPath('meta.perPage', 5);
    $response->assertJsonPath('meta.total', 0);
    $response->assertJsonPath('meta.lastPage', 1);
});
