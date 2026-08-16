<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileIdentityMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_stable_first_class_username(): void
    {
        $user = User::factory()->create([
            'name' => 'Mustafa Fares',
            'email' => null,
            'phone' => '+963944000001',
        ]);

        $this->assertNotNull($user->username);
        $this->assertMatchesRegularExpression('/^mustafa\.fares-[a-f0-9]{8}$/', (string) $user->username);
    }

    public function test_phone_only_user_can_update_profile_and_username_but_cannot_remove_all_login_identifiers(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '+963944000002',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/mobile/me/profile', [
            'name' => 'Updated User',
            'username' => 'Updated.User',
            'phone' => '+963944000002',
            'city' => 'Damascus',
            'bio' => 'Updated profile.',
        ])
            ->assertOk()
            ->assertJsonPath('data.username', 'updated.user')
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.phone', '+963944000002');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'updated.user',
            'email' => null,
        ]);

        $this->patchJson('/api/mobile/me/profile', [
            'name' => 'Updated User',
            'email' => null,
            'phone' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone'], 'error.details');
    }

    public function test_avatar_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/mobile/me/avatar', [
            'avatar' => UploadedFile::fake()->image('first.png', 160, 160),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonStructure(['data' => ['avatarUrl']]);

        $user->refresh();
        $firstPath = (string) $user->avatar_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->post('/api/mobile/me/avatar', [
            'avatar' => UploadedFile::fake()->image('second.jpg', 160, 160),
        ], ['Accept' => 'application/json'])->assertOk();

        $user->refresh();
        $secondPath = (string) $user->avatar_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->deleteJson('/api/mobile/me/avatar')
            ->assertOk()
            ->assertJsonMissingPath('data.avatarUrl');

        $user->refresh();
        $this->assertNull($user->avatar_disk);
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_public_user_publisher_returns_canonical_identity_avatar_and_aggregate_stats(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('mobile/avatars/user/avatar.png', 'avatar');

        $user = User::factory()->create([
            'username' => 'public.publisher',
            'avatar_disk' => 'public',
            'avatar_path' => 'mobile/avatars/user/avatar.png',
            'bio' => 'Publisher bio',
            'city' => 'Aleppo',
        ]);

        $first = $this->publishedPost([
            'author_id' => $user->id,
            'organization_id' => null,
            'reactions_count' => 5,
            'shares_count' => 2,
        ]);
        $this->publishedPost([
            'author_id' => $user->id,
            'organization_id' => null,
            'reactions_count' => 7,
            'shares_count' => 3,
        ]);

        $organization = Organization::factory()->create();
        $this->publishedPost([
            'author_id' => $user->id,
            'organization_id' => $organization->id,
            'reactions_count' => 100,
            'shares_count' => 100,
        ]);

        $publisher = $this->getJson("/api/mobile/discovery/publishers/{$user->id}");
        $publisher->assertOk()
            ->assertJsonPath('data.username', 'public.publisher')
            ->assertJsonPath('data.avatarUrl', $user->avatarUrl())
            ->assertJsonPath('data.bio', 'Publisher bio')
            ->assertJsonPath('data.city', 'Aleppo')
            ->assertJsonPath('data.stats.postsCount', 2)
            ->assertJsonPath('data.stats.likesCount', 12)
            ->assertJsonPath('data.stats.sharesCount', 5);

        $this->getJson("/api/mobile/discovery/posts/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.publisher.username', 'public.publisher')
            ->assertJsonPath('data.publisher.avatarUrl', $user->avatarUrl());
    }

    public function test_public_organization_publisher_stats_include_only_its_published_posts(): void
    {
        $organization = Organization::factory()->create(['status' => 'active']);
        $other = Organization::factory()->create(['status' => 'active']);

        $this->publishedPost([
            'organization_id' => $organization->id,
            'reactions_count' => 4,
            'shares_count' => 1,
        ]);
        $this->publishedPost([
            'organization_id' => $organization->id,
            'reactions_count' => 6,
            'shares_count' => 2,
        ]);
        $this->publishedPost([
            'organization_id' => $other->id,
            'reactions_count' => 100,
            'shares_count' => 100,
        ]);

        $this->getJson("/api/mobile/discovery/publishers/{$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.stats.postsCount', 2)
            ->assertJsonPath('data.stats.likesCount', 10)
            ->assertJsonPath('data.stats.sharesCount', 3);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPost(array $overrides = []): Post
    {
        return Post::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'type' => 'help_request',
            'status' => 'published',
            'reactions_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
            'published_at' => now(),
        ], $overrides));
    }
}
