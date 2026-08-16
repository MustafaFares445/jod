<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Notification;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCommentShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_create_list_update_and_delete_keep_counter_in_sync(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create(['name' => 'Mobile Commenter']);
        $post = $this->publishedPost($author);
        Sanctum::actingAs($commenter);

        $created = $this->postJson("/api/mobile/posts/{$post->id}/comments", [
            'body' => '  Helpful update.  ',
        ])->assertOk()
            ->assertJsonPath('data.postId', $post->id)
            ->assertJsonPath('data.body', 'Helpful update.')
            ->assertJsonPath('data.author.id', $commenter->id)
            ->json('data.id');

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'comments_count' => 1,
        ]);

        $this->getJson("/api/mobile/posts/{$post->id}/comments?sort=oldest")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $created);

        $this->patchJson("/api/mobile/posts/{$post->id}/comments/{$created}", [
            'body' => 'Updated comment',
        ])->assertOk()
            ->assertJsonPath('data.body', 'Updated comment');

        $this->deleteJson("/api/mobile/posts/{$post->id}/comments/{$created}")
            ->assertOk();

        $this->assertSoftDeleted('post_comments', ['id' => $created]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'comments_count' => 0,
        ]);
    }

    public function test_other_user_cannot_modify_or_delete_comment(): void
    {
        $author = User::factory()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->publishedPost($author);
        $comment = PostComment::query()->create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Owner comment',
        ]);
        $post->update(['comments_count' => 1]);
        Sanctum::actingAs($other);

        $this->patchJson("/api/mobile/posts/{$post->id}/comments/{$comment->id}", [
            'body' => 'Tampered',
        ])->assertNotFound();

        $this->deleteJson("/api/mobile/posts/{$post->id}/comments/{$comment->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('post_comments', [
            'id' => $comment->id,
            'body' => 'Owner comment',
            'deleted_at' => null,
        ]);
    }

    public function test_comment_notifies_post_author_and_maps_to_mobile_comment_type(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create(['name' => 'Commenter']);
        $post = $this->publishedPost($author);
        Sanctum::actingAs($commenter);

        $this->postJson("/api/mobile/posts/{$post->id}/comments", [
            'body' => 'New comment',
        ])->assertOk();

        $notification = Notification::query()
            ->where('recipient_id', $author->id)
            ->where('category', 'post')
            ->firstOrFail();

        $this->assertStringContainsString('comment=', (string) $notification->reference_path);

        Sanctum::actingAs($author);
        $this->getJson('/api/mobile/me/notifications?category=post')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.type', 'comment')
            ->assertJsonPath('data.0.actionLabel', 'عرض التعليق');
    }

    public function test_self_comment_does_not_create_self_notification(): void
    {
        $author = User::factory()->create();
        $post = $this->publishedPost($author);
        Sanctum::actingAs($author);

        $this->postJson("/api/mobile/posts/{$post->id}/comments", [
            'body' => 'My own update',
        ])->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'recipient_id' => $author->id,
            'category' => 'post',
        ]);
    }

    public function test_each_share_action_is_recorded_and_updates_canonical_stats(): void
    {
        $author = User::factory()->create();
        $user = User::factory()->create();
        $post = $this->publishedPost($author);
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/posts/{$post->id}/share", [
            'channel' => 'system',
        ])->assertOk()
            ->assertJsonPath('data.sharesCount', 1);

        $this->postJson("/api/mobile/posts/{$post->id}/share", [])
            ->assertOk()
            ->assertJsonPath('data.sharesCount', 2);

        $this->assertSame(2, PostShare::query()->where('post_id', $post->id)->count());
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'shares_count' => 2,
        ]);

        $this->getJson("/api/mobile/discovery/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.stats.comments', 0)
            ->assertJsonPath('data.stats.shares', 2);
    }

    public function test_discovery_most_engaged_uses_likes_comments_and_shares(): void
    {
        $author = User::factory()->create();
        $likesOnly = $this->publishedPost($author, [
            'title' => 'Likes only',
            'reactions_count' => 5,
            'comments_count' => 0,
            'shares_count' => 0,
        ]);
        $combined = $this->publishedPost($author, [
            'title' => 'Combined engagement',
            'reactions_count' => 2,
            'comments_count' => 2,
            'shares_count' => 2,
        ]);

        $this->getJson('/api/mobile/discovery/posts?sort=most_engaged&perPage=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $combined->id)
            ->assertJsonPath('data.1.id', $likesOnly->id);
    }

    public function test_comments_and_shares_reject_unpublished_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $user->id,
            'status' => 'draft',
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/mobile/posts/{$post->id}/comments")->assertNotFound();
        $this->postJson("/api/mobile/posts/{$post->id}/comments", ['body' => 'Hidden'])
            ->assertNotFound();
        $this->postJson("/api/mobile/posts/{$post->id}/share")
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPost(User $author, array $overrides = []): Post
    {
        return Post::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Published mobile post',
            'summary' => 'Summary',
            'content' => 'Published content',
            'type' => 'help_request',
            'status' => 'published',
            'author_id' => $author->id,
            'published_at' => now(),
            'reactions_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
        ], $overrides));
    }
}
