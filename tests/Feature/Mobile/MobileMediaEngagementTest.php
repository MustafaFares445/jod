<?php

declare(strict_types=1);

use App\Enums\MediaModel;
use App\Models\Media;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can like save and report a public organization reel', function () {
    Storage::fake('public');
    $organization = Organization::factory()->create(['status' => 'active']);
    $video = Media::query()->create([
        'model_type' => MediaModel::ORGANIZATION->value,
        'model_id' => $organization->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => 'videos/example.mp4',
        'original_name' => 'example.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1024,
        'position' => 0,
    ]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/mobile/media/{$video->id}/like")
        ->assertOk()
        ->assertJsonPath('data.isLiked', true)
        ->assertJsonPath('data.likesCount', 1);

    $this->postJson("/api/mobile/media/{$video->id}/save")
        ->assertOk()
        ->assertJsonPath('data.isSaved', true)
        ->assertJsonPath('data.savesCount', 1);

    $this->getJson('/api/mobile/discovery/media?perPage=20')
        ->assertOk()
        ->assertJsonPath('data.0.id', $video->id)
        ->assertJsonPath('data.0.isLiked', true)
        ->assertJsonPath('data.0.isSaved', true)
        ->assertJsonPath('data.0.organization.id', $organization->id);

    $this->postJson("/api/mobile/media/{$video->id}/reports", [
        'reason' => 'misleading',
    ])->assertOk()->assertJsonPath('data.mediaId', $video->id);

    expect(Report::query()->where('entity_type', 'media')->where('entity_id', $video->id)->exists())->toBeTrue();
});

test('authenticated user can like save and report a published post reel', function () {
    Storage::fake('public');
    $author = User::factory()->create();
    $post = Post::factory()->create([
        'author_id' => $author->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $video = Media::query()->create([
        'model_type' => MediaModel::POST->value,
        'model_id' => $post->id,
        'post_id' => $post->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => 'videos/post-reel.webm',
        'original_name' => 'post-reel.webm',
        'mime_type' => 'video/webm',
        'size' => 1024,
        'position' => 0,
    ]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/mobile/media/{$video->id}/like")
        ->assertOk()
        ->assertJsonPath('data.isLiked', true)
        ->assertJsonPath('data.likesCount', 1);

    $this->postJson("/api/mobile/media/{$video->id}/save")
        ->assertOk()
        ->assertJsonPath('data.isSaved', true)
        ->assertJsonPath('data.savesCount', 1);

    $this->getJson('/api/mobile/discovery/media?perPage=20')
        ->assertOk()
        ->assertJsonPath('data.0.id', $video->id)
        ->assertJsonPath('data.0.model', 'post')
        ->assertJsonPath('data.0.isLiked', true)
        ->assertJsonPath('data.0.isSaved', true)
        ->assertJsonPath('data.0.post.id', $post->id)
        ->assertJsonPath('data.0.publisher.id', $author->id);

    $this->postJson("/api/mobile/media/{$video->id}/reports", [
        'reason' => 'misleading',
    ])->assertOk()->assertJsonPath('data.mediaId', $video->id);

    $report = Report::query()->where('entity_type', 'media')->where('entity_id', $video->id)->first();
    expect($report)->not->toBeNull()
        ->and($report->organization_id)->toBeNull();
});
