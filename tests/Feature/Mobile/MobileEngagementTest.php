<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Organization;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Report;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_is_idempotent_and_syncs_count(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost(['reactions_count' => 7]);
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.postId', $post->id)
            ->assertJsonPath('data.isLiked', true)
            ->assertJsonPath('data.likesCount', 1);

        $this->postJson("/api/mobile/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.isLiked', true)
            ->assertJsonPath('data.likesCount', 1);

        $this->assertSame(1, PostLike::query()->where('user_id', $user->id)->where('post_id', $post->id)->count());
        $this->assertSame(1, (int) $post->refresh()->reactions_count);
    }

    public function test_unlike_is_idempotent_and_syncs_count(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost();
        PostLike::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);
        $post->update(['reactions_count' => 1]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/mobile/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.postId', $post->id)
            ->assertJsonPath('data.isLiked', false)
            ->assertJsonPath('data.likesCount', 0);

        $this->deleteJson("/api/mobile/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.isLiked', false)
            ->assertJsonPath('data.likesCount', 0);

        $this->assertDatabaseMissing('post_likes', ['user_id' => $user->id, 'post_id' => $post->id]);
        $this->assertSame(0, (int) $post->refresh()->reactions_count);
    }

    public function test_like_and_unlike_require_authentication_and_public_posts(): void
    {
        $publicPost = $this->createPost();
        $draftPost = $this->createPost(['status' => 'draft']);

        $this->postJson("/api/mobile/posts/{$publicPost->id}/like")->assertUnauthorized();
        $this->deleteJson("/api/mobile/posts/{$publicPost->id}/like")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/posts/{$draftPost->id}/like")->assertNotFound();
        $this->deleteJson("/api/mobile/posts/{$draftPost->id}/like")->assertNotFound();
    }

    public function test_save_is_idempotent_and_returns_count(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost();
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/posts/{$post->id}/save")
            ->assertOk()
            ->assertJsonPath('data.postId', $post->id)
            ->assertJsonPath('data.isSaved', true)
            ->assertJsonPath('data.savesCount', 1);

        $this->postJson("/api/mobile/posts/{$post->id}/save")
            ->assertOk()
            ->assertJsonPath('data.isSaved', true)
            ->assertJsonPath('data.savesCount', 1);

        $this->assertSame(1, SavedPost::query()->where('user_id', $user->id)->where('post_id', $post->id)->count());
    }

    public function test_unsave_is_idempotent_and_returns_count(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost();
        SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/mobile/posts/{$post->id}/save")
            ->assertOk()
            ->assertJsonPath('data.postId', $post->id)
            ->assertJsonPath('data.isSaved', false)
            ->assertJsonPath('data.savesCount', 0);

        $this->deleteJson("/api/mobile/posts/{$post->id}/save")
            ->assertOk()
            ->assertJsonPath('data.isSaved', false)
            ->assertJsonPath('data.savesCount', 0);

        $this->assertDatabaseMissing('saved_posts', ['user_id' => $user->id, 'post_id' => $post->id]);
    }

    public function test_saved_posts_are_paginated_scoped_to_user_and_public_posts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstPost = $this->createPost(['title' => 'First saved']);
        $secondPost = $this->createPost(['title' => 'Second saved']);
        $otherPost = $this->createPost(['title' => 'Other saved']);
        $draftPost = $this->createPost(['title' => 'Draft saved', 'status' => 'draft']);

        SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $firstPost->id]);
        SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $secondPost->id]);
        SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $draftPost->id]);
        SavedPost::factory()->create(['user_id' => $otherUser->id, 'post_id' => $otherPost->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/me/saved-posts?perPage=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.perPage', 10)
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonStructure(['data' => [['id', 'title', 'savedAt']]]);
        $response->assertJsonMissing(['title' => 'Other saved']);
        $response->assertJsonMissing(['title' => 'Draft saved']);
    }

    public function test_save_unsave_and_saved_posts_require_authentication_and_public_posts(): void
    {
        $publicPost = $this->createPost();
        $draftPost = $this->createPost(['status' => 'draft']);

        $this->postJson("/api/mobile/posts/{$publicPost->id}/save")->assertUnauthorized();
        $this->deleteJson("/api/mobile/posts/{$publicPost->id}/save")->assertUnauthorized();
        $this->getJson('/api/mobile/me/saved-posts')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/posts/{$draftPost->id}/save")->assertNotFound();
        $this->deleteJson("/api/mobile/posts/{$draftPost->id}/save")->assertNotFound();
    }

    public function test_report_validation_errors_are_returned(): void
    {
        $post = $this->createPost();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/posts/{$post->id}/reports", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason'], 'error.details');

        $this->postJson("/api/mobile/posts/{$post->id}/reports", ['reason' => 'ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason'], 'error.details');

        $this->postJson("/api/mobile/posts/{$post->id}/reports", [
            'reason' => 'spam',
            'details' => str_repeat('x', 181),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['details'], 'error.details');
    }

    public function test_report_creates_new_moderation_report_with_context(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $post = $this->createPost(['organization_id' => $organization->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/mobile/posts/{$post->id}/reports", [
            'reason' => 'spam',
            'details' => 'This post keeps reposting misleading content.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.postId', $post->id)
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('reports', [
            'id' => $response->json('data.id'),
            'status' => 'new',
            'category' => 'spam',
            'entity_type' => 'post',
            'entity_id' => $post->id,
            'organization_id' => $organization->id,
            'reporter_id' => $user->id,
        ]);
    }

    public function test_duplicate_reports_create_separate_records(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost();
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/posts/{$post->id}/reports", ['reason' => 'Unsafe claim'])->assertOk();
        $this->postJson("/api/mobile/posts/{$post->id}/reports", ['reason' => 'Unsafe claim'])->assertOk();

        $this->assertSame(2, Report::query()
            ->where('reporter_id', $user->id)
            ->where('entity_type', 'post')
            ->where('entity_id', $post->id)
            ->count());
    }

    public function test_report_requires_authentication_and_public_posts(): void
    {
        $publicPost = $this->createPost();
        $draftPost = $this->createPost(['status' => 'draft']);

        $this->postJson("/api/mobile/posts/{$publicPost->id}/reports", ['reason' => 'spam'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/posts/{$draftPost->id}/reports", ['reason' => 'spam'])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(array $overrides = []): Post
    {
        return Post::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Public post title',
            'summary' => 'Public post summary',
            'content' => 'Public post details.',
            'type' => 'help_request',
            'status' => 'published',
            'location' => 'Amman',
            'published_at' => now(),
        ], $overrides));
    }
}
