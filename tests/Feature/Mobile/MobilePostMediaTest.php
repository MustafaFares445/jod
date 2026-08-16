<?php

declare(strict_types=1);
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});
test('user can create post with images and receives ordered urls', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->post('/api/mobile/posts', [
        'type' => 'help_request',
        'saveAsDraft' => true,
        'images' => [
            UploadedFile::fake()->image('first.jpg', 800, 600),
            UploadedFile::fake()->image('second.png', 640, 480),
        ],
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(2, 'data.images');

    $postId = (string) $response->json('data.id');
    $images = PostImage::query()->where('post_id', $postId)->orderBy('position')->get();

    expect($images)->toHaveCount(2);
    expect($images->pluck('position')->map(fn ($value) => (int) $value)->all())->toBe([0, 1]);

    foreach ($images as $index => $image) {
        Storage::disk('public')->assertExists($image->path);
        expect($response->json("data.images.{$index}"))->toBe(Storage::disk('public')->url($image->path));
    }
});
test('user can add reorder and delete images on editable post', function () {
    $user = User::factory()->create();
    $post = mobile_post_media_test_createPost($user);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->post("/api/mobile/posts/{$post->id}/images", [
        'images' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
            UploadedFile::fake()->image('three.webp'),
        ],
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonCount(3, 'data.images');

    $images = PostImage::query()->where('post_id', $post->id)->orderBy('position')->get();
    $reversedIds = $images->pluck('id')->map(fn ($id): string => (string) $id)->reverse()->values()->all();

    $response = $this->patchJson("/api/mobile/posts/{$post->id}/images/order", [
        'imageIds' => $reversedIds,
    ]);

    $response->assertOk()->assertJsonCount(3, 'data.images');

    $reordered = PostImage::query()->where('post_id', $post->id)->orderBy('position')->get();
    expect($reordered->pluck('id')->map(fn ($id): string => (string) $id)->all())->toBe($reversedIds);

    $imageToDelete = $reordered->first();
    $pathToDelete = $imageToDelete->path;

    $this->deleteJson("/api/mobile/posts/{$post->id}/images/{$imageToDelete->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.images');

    Storage::disk('public')->assertMissing($pathToDelete);
    $this->assertDatabaseMissing('post_images', ['id' => $imageToDelete->id]);
    expect(PostImage::query()->where('post_id', $post->id)->orderBy('position')->pluck('position')->map(fn ($value) => (int) $value)->all())->toBe([0, 1]);
});
test('image limits types and complete reorder set are validated', function () {
    $user = User::factory()->create();
    $post = mobile_post_media_test_createPost($user);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->post("/api/mobile/posts/{$post->id}/images", [
        'images' => [UploadedFile::fake()->create('not-image.txt', 10, 'text/plain')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0'], 'error.details');

    $this->post("/api/mobile/posts/{$post->id}/images", [
        'images' => [
            UploadedFile::fake()->image('1.jpg'),
            UploadedFile::fake()->image('2.jpg'),
            UploadedFile::fake()->image('3.jpg'),
            UploadedFile::fake()->image('4.jpg'),
            UploadedFile::fake()->image('5.jpg'),
        ],
    ], ['Accept' => 'application/json'])->assertOk();

    $this->post("/api/mobile/posts/{$post->id}/images", [
        'images' => [UploadedFile::fake()->image('6.jpg')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images'], 'error.details');

    $firstImageId = (string) PostImage::query()->where('post_id', $post->id)->value('id');

    $this->patchJson("/api/mobile/posts/{$post->id}/images/order", [
        'imageIds' => [$firstImageId],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['imageIds'], 'error.details');
});
test('media management requires ownership and editable status', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherPost = mobile_post_media_test_createPost($otherUser);
    $publishedPost = mobile_post_media_test_createPost($user, ['status' => 'published']);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->post("/api/mobile/posts/{$otherPost->id}/images", [
        'images' => [UploadedFile::fake()->image('other.jpg')],
    ], ['Accept' => 'application/json'])->assertForbidden();

    $this->post("/api/mobile/posts/{$publishedPost->id}/images", [
        'images' => [UploadedFile::fake()->image('published.jpg')],
    ], ['Accept' => 'application/json'])->assertForbidden();
});
test('cross post image ids cannot be deleted or used for reorder', function () {
    $user = User::factory()->create();
    $firstPost = mobile_post_media_test_createPost($user);
    $secondPost = mobile_post_media_test_createPost($user);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->post("/api/mobile/posts/{$firstPost->id}/images", [
        'images' => [UploadedFile::fake()->image('first.jpg')],
    ], ['Accept' => 'application/json'])->assertOk();
    $this->post("/api/mobile/posts/{$secondPost->id}/images", [
        'images' => [UploadedFile::fake()->image('second.jpg')],
    ], ['Accept' => 'application/json'])->assertOk();

    $firstImage = PostImage::query()->where('post_id', $firstPost->id)->firstOrFail();
    $secondImage = PostImage::query()->where('post_id', $secondPost->id)->firstOrFail();

    $this->deleteJson("/api/mobile/posts/{$firstPost->id}/images/{$secondImage->id}")
        ->assertNotFound();
    $this->assertDatabaseHas('post_images', ['id' => $secondImage->id]);

    $this->patchJson("/api/mobile/posts/{$firstPost->id}/images/order", [
        'imageIds' => [(string) $secondImage->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['imageIds'], 'error.details');

    $this->assertDatabaseHas('post_images', ['id' => $firstImage->id, 'post_id' => $firstPost->id]);
});
test('discovery returns images and deleting post purges files', function () {
    $user = User::factory()->create();
    $post = mobile_post_media_test_createPost($user, ['status' => 'published', 'published_at' => now()]);
    $path = "mobile/posts/{$post->id}/existing.jpg";
    Storage::disk('public')->put($path, 'image-content');
    PostImage::query()->create([
        'post_id' => $post->id,
        'disk' => 'public',
        'path' => $path,
        'original_name' => 'existing.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 13,
        'position' => 0,
    ]);

    $this->getJson("/api/mobile/discovery/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.images.0', Storage::disk('public')->url($path));

    $post->update(['status' => 'draft', 'published_at' => null]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->deleteJson("/api/mobile/posts/{$post->id}")->assertOk();

    Storage::disk('public')->assertMissing($path);
    $this->assertDatabaseMissing('post_images', ['post_id' => $post->id]);
});
/**
 * @param  array{status?: string, published_at?: mixed, title?: string|null, content?: string|null, location?: string|null}  $overrides
 */
function mobile_post_media_test_createPost(User $user, array $overrides = []): Post
{
    return Post::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'title' => 'Mobile media post',
        'summary' => 'Mobile media summary',
        'content' => 'Mobile media details with enough content.',
        'type' => 'help_request',
        'status' => 'draft',
        'location' => 'Amman',
        'author_id' => $user->id,
    ], $overrides));
}
