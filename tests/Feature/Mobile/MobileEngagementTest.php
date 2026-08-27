<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Report;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('like and unlike are idempotent for published posts', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    foreach (['published'] as $status) {
        $post = mobile_engagement_test_createPost(['status' => $status]);

        $this->postJson("/api/mobile/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.isLiked', true)
            ->assertJsonPath('data.likesCount', 1);
        $this->postJson("/api/mobile/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.likesCount', 1);

        expect(PostLike::query()->where('user_id', $user->id)->where('post_id', $post->id)->count())->toBe(1);

        $this->deleteJson("/api/mobile/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('data.isLiked', false)
            ->assertJsonPath('data.likesCount', 0);
    }
});

test('like preserves the existing reactions counter and changes it by one only', function () {
    $user = User::factory()->create();
    $post = mobile_engagement_test_createPost(['reactions_count' => 46]);
    Sanctum::actingAs($user);

    $this->postJson("/api/mobile/posts/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('data.isLiked', true)
        ->assertJsonPath('data.likesCount', 47);

    $this->postJson("/api/mobile/posts/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('data.likesCount', 47);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'reactions_count' => 47,
    ]);

    $this->deleteJson("/api/mobile/posts/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('data.isLiked', false)
        ->assertJsonPath('data.likesCount', 46);

    $this->deleteJson("/api/mobile/posts/{$post->id}/like")
        ->assertOk()
        ->assertJsonPath('data.likesCount', 46);

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'reactions_count' => 46,
    ]);
});

test('save unsave and saved list work for published posts', function () {
    $user = User::factory()->create();
    $publishedPost = mobile_engagement_test_createPost([
        'title' => 'Published saved post',
        'status' => 'published',
    ]);
    Sanctum::actingAs($user);

    $this->postJson("/api/mobile/posts/{$publishedPost->id}/save")
        ->assertOk()
        ->assertJsonPath('data.isSaved', true)
        ->assertJsonPath('data.savesCount', 1);

    $this->getJson('/api/mobile/me/saved-posts')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $publishedPost->id)
        ->assertJsonPath('data.0.status', 'published')
        ->assertJsonPath('data.0.isSaved', true);

    $this->deleteJson("/api/mobile/posts/{$publishedPost->id}/save")
        ->assertOk()
        ->assertJsonPath('data.isSaved', false)
        ->assertJsonPath('data.savesCount', 0);

    expect(SavedPost::query()->where('user_id', $user->id)->where('post_id', $publishedPost->id)->exists())->toBeFalse();
});

test('saved posts are scoped to the authenticated user and hide non public posts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $published = mobile_engagement_test_createPost(['title' => 'Published saved']);
    $secondPublished = mobile_engagement_test_createPost(['title' => 'Second published saved', 'status' => 'published']);
    $draft = mobile_engagement_test_createPost(['title' => 'Draft saved', 'status' => 'draft']);
    $other = mobile_engagement_test_createPost(['title' => 'Other saved']);

    SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $published->id]);
    SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $secondPublished->id]);
    SavedPost::factory()->create(['user_id' => $user->id, 'post_id' => $draft->id]);
    SavedPost::factory()->create(['user_id' => $otherUser->id, 'post_id' => $other->id]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/mobile/me/saved-posts?perPage=10');

    $response->assertOk()->assertJsonPath('meta.total', 2);
    $response->assertJsonMissing(['title' => 'Draft saved']);
    $response->assertJsonMissing(['title' => 'Other saved']);
});

test('report lookup reasons match the mobile report sheet', function () {
    $response = $this->getJson('/api/mobile/lookups/report-reasons');

    $response->assertOk()
        ->assertJsonPath('data.0.code', 'misleading')
        ->assertJsonPath('data.1.code', 'abusive')
        ->assertJsonPath('data.2.code', 'fraud')
        ->assertJsonPath('data.3.code', 'impersonation')
        ->assertJsonPath('data.4.code', 'other')
        ->assertJsonPath('data.4.allowsCustomText', true);
});

test('other report reason requires custom details up to 180 characters', function () {
    $post = mobile_engagement_test_createPost();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/mobile/posts/{$post->id}/reports", [
        'reason' => 'other',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['details'], 'error.details');

    $this->postJson("/api/mobile/posts/{$post->id}/reports", [
        'reason' => 'other',
        'details' => str_repeat('x', 181),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['details'], 'error.details');
});

test('report creates moderation record with reason code label and post context', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $post = mobile_engagement_test_createPost([
        'organization_id' => $organization->id,
        'status' => 'published',
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/mobile/posts/{$post->id}/reports", [
        'reason' => 'fraud',
        'details' => 'طلب تبرع يبدو غير موثوق.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.postId', $post->id)
        ->assertJsonPath('data.status', 'new');

    $report = Report::query()->findOrFail($response->json('data.id'));
    expect($report->category)->toBe('fraud');
    expect($report->entity_type)->toBe('post');
    expect((string) $report->entity_id)->toBe((string) $post->id);
    expect($report->evidence['reason'])->toBe('fraud');
    expect($report->evidence['reasonLabel'])->toBe('احتيال أو طلب تبرع مشبوه');
    expect($report->evidence['details'])->toBe('طلب تبرع يبدو غير موثوق.');
});

test('all five report reason codes are accepted', function () {
    $user = User::factory()->create();
    $post = mobile_engagement_test_createPost();
    Sanctum::actingAs($user);

    foreach (['misleading', 'abusive', 'fraud', 'impersonation'] as $reason) {
        $this->postJson("/api/mobile/posts/{$post->id}/reports", ['reason' => $reason])
            ->assertOk();
    }

    $this->postJson("/api/mobile/posts/{$post->id}/reports", [
        'reason' => 'other',
        'details' => 'سبب مخصص للبلاغ',
    ])->assertOk();

    expect(Report::query()->where('reporter_id', $user->id)->count())->toBe(5);
});

test('engagement and reports require authentication and public post state', function () {
    $publicPost = mobile_engagement_test_createPost();
    $draftPost = mobile_engagement_test_createPost(['status' => 'draft']);

    $this->postJson("/api/mobile/posts/{$publicPost->id}/save")->assertUnauthorized();
    $this->postJson("/api/mobile/posts/{$publicPost->id}/reports", ['reason' => 'misleading'])->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/mobile/posts/{$draftPost->id}/save")->assertNotFound();
    $this->postJson("/api/mobile/posts/{$draftPost->id}/reports", ['reason' => 'misleading'])->assertNotFound();
});

/** @param array<string, mixed> $overrides */
function mobile_engagement_test_createPost(array $overrides = []): Post
{
    return Post::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'title' => 'Public post title',
        'summary' => 'Public post summary',
        'content' => 'Public post details.',
        'type' => 'help_request',
        'status' => 'published',
        'location' => 'Damascus',
        'published_at' => now(),
    ], $overrides));
}
