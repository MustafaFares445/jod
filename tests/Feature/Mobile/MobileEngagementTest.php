<?php

declare(strict_types=1);
use App\Models\Organization;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Report;
use App\Models\SavedPost;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('like is idempotent and syncs count', function () {
    $user = User::factory()->create();
    $post = mobile_engagement_test_createPost(['reactions_count' => 7]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->postJson("/api/mobile/posts/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('data.postId', $post->id)
        ->assertJsonPath('data.isLiked', true)
        ->assertJsonPath('data.likesCount', 1);

    $this->postJson("/api/mobile/posts/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('data.isLiked', true)
        ->assertJsonPath('data.likesCount', 1);

    expect(PostLike::query()->where('user_id', $user->id)->where('post_id', $post->id)->count())->toBe(1);
    expect((int) $post->refresh()->reactions_count)->toBe(1);
});
test('unlike is idempotent and syncs count', function () {
    $user = User::factory()->create();
    $post = mobile_engagement_test_createPost();
    PostLike::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);
    $post->update(['reactions_count' => 1]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

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
    expect((int) $post->refresh()->reactions_count)->toBe(0);
});
test('like and unlike require authentication and public posts', function () {
    $publicPost = mobile_engagement_test_createPost();
    $draftPost = mobile_engagement_test_createPost(['status' => 'draft']);

    $this->postJson("/api/mobile/posts/{$publicPost->id}/like")->assertUnauthorized();
    $this->deleteJson("/api/mobile/posts/{$publicPost->id}/like")->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

    $this->postJson("/api/mobile/posts/{$draftPost->id}/like")->assertNotFound();
    $this->deleteJson("/api/mobile/posts/{$draftPost->id}/like")->assertNotFound();
});
test('save is idempotent and returns count', function () {
    $user = User::factory()->create();
    $post = mobile_engagement_test_createPost();
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->postJson("/api/mobile/posts/{$post->id}/save")
        ->assertOk()
        ->assertJsonPath('data.postId', $post->id)
        ->assertJsonPath('data.isSaved', true)
        ->assertJsonPath('data.savesCount', 1);

    $this->postJson("/api/mobile/posts/{$post->id}/save")
        ->assertOk()
        ->assertJsonPath('data.isSaved', true)
        ->assertJsonPath('data.savesCount', 1);

    expect(SavedPost::query()->where('user_id', $user->id)->where('post_id', $post->id)->count())->toBe(1);
});
test('unsave is idempotent and returns count', function () {
    $user = User::factory()->create();
    $post = mobile_engagement_test_createPost();
    SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

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
});
test('saved posts are paginated scoped to user and public posts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $firstPost = mobile_engagement_test_createPost(['title' => 'First saved']);
    $secondPost = mobile_engagement_test_createPost(['title' => 'Second saved']);
    $otherPost = mobile_engagement_test_createPost(['title' => 'Other saved']);
    $draftPost = mobile_engagement_test_createPost(['title' => 'Draft saved', 'status' => 'draft']);

    SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $firstPost->id]);
    SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $secondPost->id]);
    SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $draftPost->id]);
    SavedPost::factory()->create(['user_id' => $otherUser->id, 'post_id' => $otherPost->id]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->getJson('/api/mobile/me/saved-posts?perPage=10');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('data.0.status', 'published')
        ->assertJsonStructure(['data' => [['id', 'title', 'savedAt']]]);
    $response->assertJsonMissing(['title' => 'Other saved']);
    $response->assertJsonMissing(['title' => 'Draft saved']);
});
test('save unsave and saved posts require authentication and public posts', function () {
    $publicPost = mobile_engagement_test_createPost();
    $draftPost = mobile_engagement_test_createPost(['status' => 'draft']);

    $this->postJson("/api/mobile/posts/{$publicPost->id}/save")->assertUnauthorized();
    $this->deleteJson("/api/mobile/posts/{$publicPost->id}/save")->assertUnauthorized();
    $this->getJson('/api/mobile/me/saved-posts')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

    $this->postJson("/api/mobile/posts/{$draftPost->id}/save")->assertNotFound();
    $this->deleteJson("/api/mobile/posts/{$draftPost->id}/save")->assertNotFound();
});
test('report validation errors are returned', function () {
    $post = mobile_engagement_test_createPost();
    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

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
});
test('report creates new moderation report with context', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $post = mobile_engagement_test_createPost(['organization_id' => $organization->id]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

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
});
test('duplicate reports create separate records', function () {
    $user = User::factory()->create();
    $post = mobile_engagement_test_createPost();
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->postJson("/api/mobile/posts/{$post->id}/reports", ['reason' => 'Unsafe claim'])->assertOk();
    $this->postJson("/api/mobile/posts/{$post->id}/reports", ['reason' => 'Unsafe claim'])->assertOk();

    expect(Report::query()
        ->where('reporter_id', $user->id)
        ->where('entity_type', 'post')
        ->where('entity_id', $post->id)
        ->count())->toBe(2);
});
test('report requires authentication and public posts', function () {
    $publicPost = mobile_engagement_test_createPost();
    $draftPost = mobile_engagement_test_createPost(['status' => 'draft']);

    $this->postJson("/api/mobile/posts/{$publicPost->id}/reports", ['reason' => 'spam'])->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

    $this->postJson("/api/mobile/posts/{$draftPost->id}/reports", ['reason' => 'spam'])->assertNotFound();
});
/**
 * @param  array<string, mixed>  $overrides
 */
function mobile_engagement_test_createPost(array $overrides = []): Post
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
