<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('mobile post create body does not accept media files', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->post('/api/mobile/posts', [
        'type' => 'help_request',
        'saveAsDraft' => true,
        'images' => [UploadedFile::fake()->image('embedded.jpg')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images'], 'error.details');
});

test('personal post images use the general media manager one file per request', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $draft = $this->postJson('/api/mobile/posts', [
        'type' => 'help_request',
        'title' => 'Need winter supplies',
        'details' => 'A family needs winter supplies and warm clothes.',
        'city' => 'Damascus',
        'saveAsDraft' => true,
    ])->assertOk()->assertJsonPath('data.status', 'draft');

    $postId = (string) $draft->json('data.id');

    $first = $this->post("/api/v1/media/post/{$postId}/images", [
        'file' => UploadedFile::fake()->image('first.jpg', 800, 600),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.model', 'post')
        ->assertJsonPath('data.modelId', $postId)
        ->assertJsonPath('data.prop', 'images')
        ->assertJsonPath('data.position', 0);

    $second = $this->post("/api/v1/media/post/{$postId}/images", [
        'file' => UploadedFile::fake()->image('second.webp', 640, 480),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.position', 1);

    expect($first->json('data.id'))->not->toBe($second->json('data.id'));
    expect(mobile_post_media($postId))->toHaveCount(2);

    $this->getJson("/api/mobile/me/posts/{$postId}")
        ->assertOk()
        ->assertJsonCount(2, 'data.images')
        ->assertJsonCount(2, 'data.imageMedia')
        ->assertJsonPath('data.imageMedia.0.id', $first->json('data.id'))
        ->assertJsonPath('data.imageMedia.1.id', $second->json('data.id'));
});

test('personal post media can be replaced and deleted by media id', function () {
    $user = User::factory()->create();
    $post = mobile_post_media_test_createPost($user);
    Sanctum::actingAs($user);

    $uploaded = $this->post("/api/v1/media/post/{$post->id}/images", [
        'file' => UploadedFile::fake()->image('before.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertCreated();

    $mediaId = (string) $uploaded->json('data.id');
    $oldPath = mobile_post_media((string) $post->id)->firstOrFail()->path;

    $this->post("/api/v1/media/post/{$post->id}/images/{$mediaId}/replace", [
        'file' => UploadedFile::fake()->image('after.png'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.id', $mediaId)
        ->assertJsonPath('data.originalName', 'after.png')
        ->assertJsonPath('data.position', 0);

    Storage::disk('public')->assertMissing($oldPath);
    $replacement = mobile_post_media((string) $post->id)->firstOrFail();
    Storage::disk('public')->assertExists($replacement->path);

    $this->deleteJson("/api/v1/media/post/{$post->id}/images/{$mediaId}")
        ->assertOk();

    Storage::disk('public')->assertMissing($replacement->path);
    $this->assertDatabaseMissing('media', ['id' => $mediaId]);
});

test('general media manager enforces personal post ownership and editable status', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherPost = mobile_post_media_test_createPost($otherUser);
    $pendingPost = mobile_post_media_test_createPost($user, ['status' => 'pending']);
    $approvedPost = mobile_post_media_test_createPost($user, ['status' => 'approved', 'published_at' => now()]);
    Sanctum::actingAs($user);

    foreach ([$otherPost, $pendingPost, $approvedPost] as $post) {
        $this->post("/api/v1/media/post/{$post->id}/images", [
            'file' => UploadedFile::fake()->image('blocked.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }
});

test('draft can upload media then be submitted for review', function () {
    $user = User::factory()->create();
    $post = mobile_post_media_test_createPost($user);
    Sanctum::actingAs($user);

    $this->post("/api/v1/media/post/{$post->id}/images", [
        'file' => UploadedFile::fake()->image('review.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->postJson("/api/mobile/posts/{$post->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonCount(1, 'data.images');

    $this->post("/api/v1/media/post/{$post->id}/images", [
        'file' => UploadedFile::fake()->image('too-late.jpg'),
    ], ['Accept' => 'application/json'])->assertForbidden();
});

test('deleting a personal post purges general media files', function () {
    $user = User::factory()->create();
    $post = mobile_post_media_test_createPost($user);
    Sanctum::actingAs($user);

    $this->post("/api/v1/media/post/{$post->id}/images", [
        'file' => UploadedFile::fake()->image('delete-me.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $media = mobile_post_media((string) $post->id)->firstOrFail();
    Storage::disk('public')->assertExists($media->path);

    $this->deleteJson("/api/mobile/posts/{$post->id}")->assertOk();

    Storage::disk('public')->assertMissing($media->path);
    $this->assertDatabaseMissing('media', [
        'model_type' => 'post',
        'model_id' => $post->id,
        'prop' => 'images',
    ]);
});

function mobile_post_media(string $postId)
{
    return Media::query()
        ->where('model_type', 'post')
        ->where('model_id', $postId)
        ->where('prop', 'images')
        ->orderBy('position')
        ->orderBy('id')
        ->get();
}

/** @param array<string, mixed> $overrides */
function mobile_post_media_test_createPost(User $user, array $overrides = []): Post
{
    return Post::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'title' => 'Mobile media post',
        'summary' => 'Mobile media summary',
        'content' => 'Mobile media details with enough content.',
        'type' => 'help_request',
        'status' => 'draft',
        'location' => 'Damascus',
        'author_id' => $user->id,
    ], $overrides));
}
