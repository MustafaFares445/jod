<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobilePostMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_user_can_create_post_with_images_and_receives_ordered_urls(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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

        $this->assertCount(2, $images);
        $this->assertSame([0, 1], $images->pluck('position')->map(fn ($value) => (int) $value)->all());

        foreach ($images as $index => $image) {
            Storage::disk('public')->assertExists($image->path);
            $this->assertSame(
                Storage::disk('public')->url($image->path),
                $response->json("data.images.{$index}"),
            );
        }
    }

    public function test_user_can_add_reorder_and_delete_images_on_editable_post(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user);
        Sanctum::actingAs($user);

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
        $this->assertSame($reversedIds, $reordered->pluck('id')->map(fn ($id): string => (string) $id)->all());

        $imageToDelete = $reordered->first();
        $pathToDelete = $imageToDelete->path;

        $this->deleteJson("/api/mobile/posts/{$post->id}/images/{$imageToDelete->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.images');

        Storage::disk('public')->assertMissing($pathToDelete);
        $this->assertDatabaseMissing('post_images', ['id' => $imageToDelete->id]);
        $this->assertSame(
            [0, 1],
            PostImage::query()->where('post_id', $post->id)->orderBy('position')->pluck('position')->map(fn ($value) => (int) $value)->all(),
        );
    }

    public function test_image_limits_types_and_complete_reorder_set_are_validated(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user);
        Sanctum::actingAs($user);

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
    }

    public function test_media_management_requires_ownership_and_editable_status(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherPost = $this->createPost($otherUser);
        $publishedPost = $this->createPost($user, ['status' => 'published']);
        Sanctum::actingAs($user);

        $this->post("/api/mobile/posts/{$otherPost->id}/images", [
            'images' => [UploadedFile::fake()->image('other.jpg')],
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->post("/api/mobile/posts/{$publishedPost->id}/images", [
            'images' => [UploadedFile::fake()->image('published.jpg')],
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_cross_post_image_ids_cannot_be_deleted_or_used_for_reorder(): void
    {
        $user = User::factory()->create();
        $firstPost = $this->createPost($user);
        $secondPost = $this->createPost($user);
        Sanctum::actingAs($user);

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
    }

    public function test_discovery_returns_images_and_deleting_post_purges_files(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user, ['status' => 'published', 'published_at' => now()]);
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
        Sanctum::actingAs($user);

        $this->deleteJson("/api/mobile/posts/{$post->id}")->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('post_images', ['post_id' => $post->id]);
    }

    /**
     * @param  array{status?: string, published_at?: mixed, title?: string|null, content?: string|null, location?: string|null}  $overrides
     */
    private function createPost(User $user, array $overrides = []): Post
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
}
