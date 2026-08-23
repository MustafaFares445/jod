<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('mobile registration requires name email phone password and confirmation', function () {
    $base = [
        'name' => 'Required Fields User',
        'email' => 'required-fields@example.com',
        'phone' => '+963900000001',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    foreach (array_keys($base) as $requiredField) {
        $payload = $base;
        unset($payload[$requiredField]);

        $this->postJson('/api/mobile/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$requiredField], 'error.details');
    }
});

test('mobile user can update contact city and bio separately from password', function () {
    $user = User::factory()->create([
        'phone' => '+963900000002',
        'city' => null,
        'bio' => null,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/mobile/me/profile', [
        'name' => 'Updated Mobile Name',
        'email' => 'updated-profile@example.com',
        'phone' => '+963900000003',
        'city' => 'Damascus',
        'bio' => 'متطوع مهتم بالمبادرات المجتمعية.',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Mobile Name')
        ->assertJsonPath('data.email', 'updated-profile@example.com')
        ->assertJsonPath('data.phone', '+963900000003')
        ->assertJsonPath('data.city', 'Damascus')
        ->assertJsonPath('data.bio', 'متطوع مهتم بالمبادرات المجتمعية.');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'city' => 'Damascus',
        'bio' => 'متطوع مهتم بالمبادرات المجتمعية.',
    ]);
});

test('my posts expose draft pending active rejected and archived states', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    mobile_user_flow_post($user, ['title' => 'Draft item', 'status' => 'draft']);
    mobile_user_flow_post($user, ['title' => 'Pending item', 'status' => 'pending']);
    mobile_user_flow_post($user, ['title' => 'Published item', 'status' => 'published', 'published_at' => now()->subDay()]);
    mobile_user_flow_post($user, ['title' => 'Approved item', 'status' => 'approved', 'published_at' => now()]);
    mobile_user_flow_post($user, ['title' => 'Rejected item', 'status' => 'rejected', 'rejection_reason' => 'Needs more details']);
    mobile_user_flow_post($user, ['title' => 'Archived item', 'status' => 'archived']);

    $expectations = [
        'draft' => 1,
        'pending' => 1,
        'active' => 2,
        'rejected' => 1,
        'archived' => 1,
    ];

    foreach ($expectations as $status => $total) {
        $response = $this->getJson('/api/mobile/me/posts?filter[status]='.$status.'&perPage=20');
        $response->assertOk()->assertJsonPath('meta.total', $total);

        if ($status === 'active') {
            foreach ($response->json('data') as $item) {
                expect($item['status'])->toBe('active');
            }
        }
    }
});

test('owner can fetch personal post detail while another user cannot', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $post = mobile_user_flow_post($owner, [
        'title' => 'Private draft detail',
        'status' => 'draft',
    ]);

    Sanctum::actingAs($owner);
    $this->getJson("/api/mobile/me/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.title', 'Private draft detail')
        ->assertJsonPath('data.status', 'draft');

    Sanctum::actingAs($other);
    $this->getJson("/api/mobile/me/posts/{$post->id}")->assertForbidden();
});

test('global search returns accounts posts and campaigns with requested filters', function () {
    $category = Category::factory()->create([
        'name' => 'health',
        'target' => 'post',
        'status' => 'active',
    ]);
    $organization = Organization::factory()->create([
        'name' => 'Winter Relief Organization',
        'location' => 'Damascus',
        'status' => 'active',
    ]);
    $publisher = User::factory()->create([
        'name' => 'Samar Search',
        'city' => 'Damascus',
        'status' => 'active',
    ]);

    $olderPost = mobile_user_flow_post($publisher, [
        'title' => 'Older Health Post',
        'status' => 'published',
        'location' => 'Damascus',
        'category_id' => $category->id,
        'published_at' => now()->subDays(10),
        'created_at' => now()->subDays(10),
    ]);
    $newerPost = mobile_user_flow_post($publisher, [
        'title' => 'Newer Health Post',
        'status' => 'approved',
        'location' => 'Damascus',
        'category_id' => $category->id,
        'published_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ]);
    mobile_user_flow_post(User::factory()->create(), [
        'title' => 'Outside location',
        'status' => 'published',
        'location' => 'Aleppo',
        'category_id' => $category->id,
        'published_at' => now(),
    ]);

    mobile_user_flow_post($publisher, [
        'title' => 'Publisher visibility post',
        'status' => 'published',
        'location' => 'Damascus',
        'published_at' => now(),
    ]);
    mobile_user_flow_post($publisher, [
        'title' => 'Organization visibility post',
        'status' => 'published',
        'organization_id' => $organization->id,
        'location' => 'Damascus',
        'published_at' => now(),
    ]);

    $campaign = Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'title' => 'Winter Health Campaign',
        'summary' => 'Health support campaign for winter.',
        'category' => 'health',
        'status' => 'active',
        'location' => 'Damascus',
        'goal_amount' => 1000,
        'raised_amount' => 100,
        'beneficiaries_count' => 20,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $posts = $this->getJson('/api/mobile/search?type=posts&location=Damascus&category=health&sort=oldest&perType=10');
    $posts->assertOk()
        ->assertJsonPath('data.posts.0.id', $olderPost->id)
        ->assertJsonPath('data.posts.1.id', $newerPost->id)
        ->assertJsonPath('meta.appliedFilters.location', 'Damascus')
        ->assertJsonPath('meta.appliedFilters.category', 'health');

    $this->getJson('/api/mobile/search?type=accounts&search=Samar')
        ->assertOk()
        ->assertJsonPath('data.accounts.0.id', $publisher->id)
        ->assertJsonPath('data.accounts.0.accountType', 'user');

    $this->getJson('/api/mobile/search?type=campaigns&search=Winter&location=Damascus&category=health')
        ->assertOk()
        ->assertJsonPath('data.campaigns.0.id', $campaign->id);

    $this->getJson('/api/mobile/search?search=Winter')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['accounts', 'posts', 'campaigns'],
            'meta' => ['counts', 'appliedFilters'],
        ]);
});

/** @param array<string, mixed> $overrides */
function mobile_user_flow_post(User $user, array $overrides = []): Post
{
    return Post::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'title' => 'Mobile user flow post',
        'summary' => 'Mobile user flow summary',
        'content' => 'Mobile user flow content with enough details.',
        'type' => 'help_request',
        'status' => 'draft',
        'location' => 'Damascus',
        'author_id' => $user->id,
    ], $overrides));
}
