<?php

declare(strict_types=1);
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('my posts returns only owned posts with pagination', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    $ownedPost = mobile_publishing_test_createPost($user, [
        'title' => 'Owned post',
        'status' => 'draft',
        'views_count' => 9,
        'reactions_count' => 4,
    ]);
    mobile_publishing_test_createPost($otherUser, ['title' => 'Other post', 'status' => 'draft']);

    $response = $this->getJson('/api/mobile/me/posts?perPage=10');

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.0.id', $ownedPost->id);
    $response->assertJsonPath('data.0.ownerId', $user->id);
    $response->assertJsonPath('data.0.viewsCount', 9);
    $response->assertJsonPath('data.0.reactionsCount', 4);
    $response->assertJsonPath('data.0.commentsCount', 0);
    $response->assertJsonPath('data.0.sharesCount', 0);
    $response->assertJsonPath('data.0.stats.likes', 4);
    $response->assertJsonMissing(['title' => 'Other post']);
    $response->assertJsonPath('meta.currentPage', 1);
    $response->assertJsonPath('meta.perPage', 10);
    $response->assertJsonPath('meta.total', 1);
});
test('my posts filters active as internal published', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $publishedPost = mobile_publishing_test_createPost($user, ['title' => 'Published post', 'status' => 'published']);
    mobile_publishing_test_createPost($user, ['title' => 'Draft post', 'status' => 'draft']);

    $response = $this->getJson('/api/mobile/me/posts?filter[status]=active');

    $response->assertOk();
    $response->assertJsonPath('data.0.id', $publishedPost->id);
    $response->assertJsonPath('data.0.status', 'active');
    $response->assertJsonMissing(['title' => 'Draft post']);
});
test('create draft allows incomplete fields and assigns owner', function () {
test('user can create and submit a service offer post', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/mobile/posts', [
        'type' => 'service_offer',
        'title' => 'I can provide tutoring',
        'details' => 'I can provide free math tutoring for students in the city.',
        'city' => 'Damascus',
        'saveAsDraft' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.type', 'service_offer')
        ->assertJsonPath('data.status', 'pending');
});


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
});
test('create submitted post requires full validation and becomes pending', function () {
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
});
test('create rejects non empty images until uploads are supported', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/mobile/posts', [
        'type' => 'help_request',
        'title' => 'Food support needed',
        'details' => 'Family needs food support this week.',
        'city' => 'Amman',
        'images' => ['image-1.jpg'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['images'], 'error.details');
});
test('owner can update draft or rejected post without changing status', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $draftPost = mobile_publishing_test_createPost($user, ['status' => 'draft']);
    $rejectedPost = mobile_publishing_test_createPost($user, ['status' => 'rejected', 'rejection_reason' => 'Too short']);

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
});
test('update denies pending active archived and non owned posts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    foreach (['pending', 'published', 'archived'] as $status) {
        $post = mobile_publishing_test_createPost($user, ['status' => $status]);

        $this->patchJson("/api/mobile/posts/{$post->id}", validPayload())
            ->assertForbidden();
    }

    $otherPost = mobile_publishing_test_createPost($otherUser, ['status' => 'draft']);

    $this->patchJson("/api/mobile/posts/{$otherPost->id}", validPayload())
        ->assertForbidden();
});
test('owner can submit draft and resubmit rejected post', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $draftPost = mobile_publishing_test_createPost($user, ['status' => 'draft']);
    $rejectedPost = mobile_publishing_test_createPost($user, ['status' => 'rejected', 'rejection_reason' => 'Needs details']);

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
});
test('submit requires complete persisted post and ownership', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);
    $incompletePost = mobile_publishing_test_createPost($user, [
        'title' => null,
        'content' => null,
        'location' => null,
        'status' => 'draft',
    ]);
    $otherPost = mobile_publishing_test_createPost($otherUser, ['status' => 'draft']);

    $this->postJson("/api/mobile/posts/{$incompletePost->id}/submit")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'details', 'city'], 'error.details');

    $this->postJson("/api/mobile/posts/{$otherPost->id}/submit")
        ->assertForbidden();
});
test('owner can archive active post', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $post = mobile_publishing_test_createPost($user, ['status' => 'published']);

    $this->postJson("/api/mobile/posts/{$post->id}/archive")
        ->assertOk()
        ->assertJsonPath('message', 'Post archived successfully.')
        ->assertJsonPath('data.status', 'archived');

    $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'archived']);
});
test('owner can repost archived post as active', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $post = mobile_publishing_test_createPost($user, ['status' => 'archived', 'published_at' => null]);

    $this->postJson("/api/mobile/posts/{$post->id}/repost")
        ->assertOk()
        ->assertJsonPath('message', 'Post reposted successfully.')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'published']);
    expect($post->refresh()->published_at)->not->toBeNull();
});
test('owner can delete post and hide it from mobile lists', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $post = mobile_publishing_test_createPost($user, ['status' => 'published']);

    $this->deleteJson("/api/mobile/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Post deleted successfully.')
        ->assertJsonPath('data', null);

    $this->assertSoftDeleted('posts', ['id' => $post->id]);
    $this->getJson('/api/mobile/me/posts')->assertOk()->assertJsonMissing(['id' => $post->id]);
    $this->getJson('/api/mobile/discovery/posts')->assertOk()->assertJsonMissing(['id' => $post->id]);
});
test('archive repost and delete deny non owner', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    $activePost = mobile_publishing_test_createPost($otherUser, ['status' => 'published']);
    $archivedPost = mobile_publishing_test_createPost($otherUser, ['status' => 'archived']);
    $draftPost = mobile_publishing_test_createPost($otherUser, ['status' => 'draft']);

    $this->postJson("/api/mobile/posts/{$activePost->id}/archive")->assertForbidden();
    $this->postJson("/api/mobile/posts/{$archivedPost->id}/repost")->assertForbidden();
    $this->deleteJson("/api/mobile/posts/{$draftPost->id}")->assertForbidden();
});
/**
 * @param  array<string, mixed>  $overrides
 */
function mobile_publishing_test_createPost(User $user, array $overrides = []): Post
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
function validPayload(): array
{
    return [
        'type' => 'help_request',
        'title' => 'Valid post title',
        'details' => 'Valid details for a mobile post.',
        'city' => 'Amman',
    ];
}
