<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobilePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_posts_returns_only_owned_posts_with_pagination(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $ownedPost = $this->createPost($user, ['title' => 'Owned post', 'status' => 'draft']);
        $this->createPost($otherUser, ['title' => 'Other post', 'status' => 'draft']);

        $response = $this->getJson('/api/mobile/me/posts?perPage=10');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.id', $ownedPost->id);
        $response->assertJsonPath('data.0.ownerId', $user->id);
        $response->assertJsonMissing(['title' => 'Other post']);
        $response->assertJsonPath('meta.currentPage', 1);
        $response->assertJsonPath('meta.perPage', 10);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_my_posts_filters_active_as_internal_published(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $publishedPost = $this->createPost($user, ['title' => 'Published post', 'status' => 'published']);
        $this->createPost($user, ['title' => 'Draft post', 'status' => 'draft']);

        $response = $this->getJson('/api/mobile/me/posts?filter[status]=active');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $publishedPost->id);
        $response->assertJsonPath('data.0.status', 'active');
        $response->assertJsonMissing(['title' => 'Draft post']);
    }

    public function test_create_draft_allows_incomplete_fields_and_assigns_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/mobile/posts', [
            'type' => 'help_request',
            'saveAsDraft' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Draft saved successfully.');
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.ownerId', $user->id);

        $this->assertDatabaseHas('posts', [
            'author_id' => $user->id,
            'type' => 'help_request',
            'status' => 'draft',
            'title' => null,
        ]);
    }

    public function test_create_submitted_post_requires_full_validation_and_becomes_pending(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['target' => 'post', 'status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/posts', [
            'type' => 'help_request',
            'saveAsDraft' => false,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonValidationErrors(['title', 'details', 'city'], 'error.details');

        $response = $this->postJson('/api/mobile/posts', [
            'type' => 'donation_campaign',
            'title' => 'Food support needed',
            'details' => 'Family needs food support this week.',
            'city' => 'Amman',
            'categoryId' => $category->id,
            'saveAsDraft' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Post submitted for review.');
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.categoryId', $category->id);

        $this->assertDatabaseHas('posts', [
            'author_id' => $user->id,
            'type' => 'donation_campaign',
            'status' => 'pending',
            'location' => 'Amman',
            'category_id' => $category->id,
        ]);
    }

    public function test_create_rejects_non_empty_images_until_uploads_are_supported(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/mobile/posts', [
            'type' => 'help_request',
            'title' => 'Food support needed',
            'details' => 'Family needs food support this week.',
            'city' => 'Amman',
            'images' => ['image-1.jpg'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['images'], 'error.details');
    }

    public function test_owner_can_update_draft_or_rejected_post_without_changing_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $draftPost = $this->createPost($user, ['status' => 'draft']);
        $rejectedPost = $this->createPost($user, ['status' => 'rejected', 'rejection_reason' => 'Too short']);

        $this->patchJson("/api/mobile/posts/{$draftPost->id}", [
            'type' => 'volunteer_opportunity',
            'title' => 'Updated draft title',
            'details' => 'Updated details for the draft post.',
            'city' => 'Irbid',
            'status' => 'published',
        ])->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Updated draft title');

        $this->patchJson("/api/mobile/posts/{$rejectedPost->id}", [
            'type' => 'help_request',
            'title' => 'Rejected post updated',
            'details' => 'Updated details for rejected content.',
            'city' => 'Zarqa',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('posts', [
            'id' => $draftPost->id,
            'status' => 'draft',
            'title' => 'Updated draft title',
        ]);
    }

    public function test_update_denies_pending_active_archived_and_non_owned_posts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (['pending', 'published', 'archived'] as $status) {
            $post = $this->createPost($user, ['status' => $status]);

            $this->patchJson("/api/mobile/posts/{$post->id}", $this->validPayload())
                ->assertForbidden();
        }

        $otherPost = $this->createPost($otherUser, ['status' => 'draft']);

        $this->patchJson("/api/mobile/posts/{$otherPost->id}", $this->validPayload())
            ->assertForbidden();
    }

    public function test_owner_can_submit_draft_and_resubmit_rejected_post(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $draftPost = $this->createPost($user, ['status' => 'draft']);
        $rejectedPost = $this->createPost($user, ['status' => 'rejected', 'rejection_reason' => 'Needs details']);

        $this->postJson("/api/mobile/posts/{$draftPost->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rejectionReason', null);

        $this->postJson("/api/mobile/posts/{$rejectedPost->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rejectionReason', null);

        $this->assertDatabaseHas('posts', [
            'id' => $rejectedPost->id,
            'status' => 'pending',
            'rejection_reason' => null,
        ]);
    }

    public function test_submit_requires_complete_persisted_post_and_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);
        $incompletePost = $this->createPost($user, [
            'title' => null,
            'content' => null,
            'location' => null,
            'status' => 'draft',
        ]);
        $otherPost = $this->createPost($otherUser, ['status' => 'draft']);

        $this->postJson("/api/mobile/posts/{$incompletePost->id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'details', 'city'], 'error.details');

        $this->postJson("/api/mobile/posts/{$otherPost->id}/submit")
            ->assertForbidden();
    }

    public function test_owner_can_archive_active_post(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = $this->createPost($user, ['status' => 'published']);

        $this->postJson("/api/mobile/posts/{$post->id}/archive")
            ->assertOk()
            ->assertJsonPath('message', 'Post archived successfully.')
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'archived']);
    }

    public function test_owner_can_repost_archived_post_as_active(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = $this->createPost($user, ['status' => 'archived', 'published_at' => null]);

        $this->postJson("/api/mobile/posts/{$post->id}/repost")
            ->assertOk()
            ->assertJsonPath('message', 'Post reposted successfully.')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'published']);
        $this->assertNotNull($post->refresh()->published_at);
    }

    public function test_owner_can_delete_post_and_hide_it_from_mobile_lists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = $this->createPost($user, ['status' => 'published']);

        $this->deleteJson("/api/mobile/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Post deleted successfully.')
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->getJson('/api/mobile/me/posts')->assertOk()->assertJsonMissing(['id' => $post->id]);
        $this->getJson('/api/mobile/discovery/posts')->assertOk()->assertJsonMissing(['id' => $post->id]);
    }

    public function test_archive_repost_and_delete_deny_non_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $activePost = $this->createPost($otherUser, ['status' => 'published']);
        $archivedPost = $this->createPost($otherUser, ['status' => 'archived']);
        $draftPost = $this->createPost($otherUser, ['status' => 'draft']);

        $this->postJson("/api/mobile/posts/{$activePost->id}/archive")->assertForbidden();
        $this->postJson("/api/mobile/posts/{$archivedPost->id}/repost")->assertForbidden();
        $this->deleteJson("/api/mobile/posts/{$draftPost->id}")->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(User $user, array $overrides = []): Post
    {
        return Post::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Mobile post title',
            'summary' => 'Mobile post summary',
            'content' => 'Mobile post details with enough content.',
            'type' => 'help_request',
            'status' => 'draft',
            'location' => 'Amman',
            'author_id' => $user->id,
        ], $overrides));
    }

    /**
     * @return array{type: string, title: string, details: string, city: string}
     */
    private function validPayload(): array
    {
        return [
            'type' => 'help_request',
            'title' => 'Valid post title',
            'details' => 'Valid details for a mobile post.',
            'city' => 'Amman',
        ];
    }
}
