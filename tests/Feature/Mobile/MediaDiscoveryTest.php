<?php

declare(strict_types=1);

use App\Enums\MediaModel;
use App\Models\Media;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

test('organization media discovery includes the organization and its logo relationship', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Media Discovery Organization',
        'email' => 'media-discovery@example.test',
        'status' => 'active',
        'verification_status' => 'verified',
    ]);

    $logo = Media::query()->create([
        'model_type' => MediaModel::ORGANIZATION->value,
        'model_id' => $organization->id,
        'prop' => 'logo',
        'disk' => 'public',
        'path' => 'organizations/logo.png',
        'original_name' => 'logo.png',
        'mime_type' => 'image/png',
        'size' => 1234,
        'position' => 0,
    ]);

    $previewPath = 'organizations/video-preview.mp4';

    $video = Media::query()->create([
        'model_type' => MediaModel::ORGANIZATION->value,
        'model_id' => $organization->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => 'organizations/video.mp4',
        'preview_disk' => 'public',
        'preview_path' => $previewPath,
        'preview_mime_type' => 'video/mp4',
        'preview_size' => 123,
        'preview_status' => 'ready',
        'original_name' => 'video.mp4',
        'mime_type' => 'video/mp4',
        'size' => 4567,
        'position' => 0,
    ]);

    $previewUrl = route('mobile.discovery.media.preview', [
        'video' => $video->id,
        'v' => substr(sha1($previewPath), 0, 12),
    ]);

    $this->getJson('/api/mobile/discovery/media')
        ->assertOk()
        ->assertJsonPath('data.0.id', $video->id)
        ->assertJsonPath('data.0.streamUrl', route('mobile.discovery.media.stream', ['video' => $video->id]))
        ->assertJsonPath('data.0.previewUrl', $previewUrl)
        ->assertJsonPath('data.0.previewStatus', 'ready')
        ->assertJsonPath('data.0.previewMimeType', 'video/mp4')
        ->assertJsonPath('data.0.previewSize', 123)
        ->assertJsonPath('data.0.organization.id', $organization->id)
        ->assertJsonPath('data.0.organization.name', $organization->name)
        ->assertJsonPath('data.0.organization.verified', true)
        ->assertJsonPath('data.0.organization.logo.id', $logo->id)
        ->assertJsonPath('data.0.organization.logo.model', 'organization')
        ->assertJsonPath('data.0.organization.logo.prop', 'logo');

    $this->getJson("/api/mobile/discovery/media/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.streamUrl', route('mobile.discovery.media.stream', ['video' => $video->id]))
        ->assertJsonPath('data.previewUrl', $previewUrl)
        ->assertJsonPath('data.previewStatus', 'ready')
        ->assertJsonPath('data.organization.id', $organization->id)
        ->assertJsonPath('data.organization.verified', true)
        ->assertJsonPath('data.organization.logo.id', $logo->id)
        ->assertJsonPath('data.organization.logo.prop', 'logo');
});

test('media discovery includes videos attached to published posts', function (): void {
    $author = User::factory()->create([
        'name' => 'مدير النظام',
        'email' => 'content-admin@example.test',
    ]);
    $post = Post::factory()->create([
        'author_id' => $author->id,
        'title' => 'فيديو توعوي من سوريا',
        'summary' => 'محتوى مصور مرتبط بالتوعية المجتمعية.',
        'content' => 'محتوى طبيعي مخصص للعرض في التطبيق.',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $video = Media::query()->create([
        'model_type' => MediaModel::POST->value,
        'model_id' => $post->id,
        'post_id' => $post->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => "posts/{$post->id}/video.webm",
        'original_name' => 'video.webm',
        'description' => 'محتوى مصور مرتبط بالتوعية المجتمعية.',
        'mime_type' => 'video/webm',
        'size' => 4567,
        'position' => 0,
        'preview_status' => 'pending',
    ]);

    $this->getJson('/api/mobile/discovery/media')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $video->id)
        ->assertJsonPath('data.0.model', 'post')
        ->assertJsonPath('data.0.post.id', $post->id)
        ->assertJsonPath('data.0.post.title', $post->title)
        ->assertJsonPath('data.0.publisher.id', $author->id)
        ->assertJsonPath('data.0.publisher.name', 'مدير النظام')
        ->assertJsonPath('data.0.previewStatus', 'pending')
        ->assertJsonPath('data.0.streamUrl', route('mobile.discovery.media.stream', ['video' => $video->id]));

    $this->getJson("/api/mobile/discovery/media/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.model', 'post')
        ->assertJsonPath('data.post.id', $post->id)
        ->assertJsonPath('data.publisher.id', $author->id);
});

test('media discovery hides videos attached to unpublished posts', function (): void {
    $post = Post::factory()->create(['status' => 'draft']);
    $video = Media::query()->create([
        'model_type' => MediaModel::POST->value,
        'model_id' => $post->id,
        'post_id' => $post->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => "posts/{$post->id}/draft-video.webm",
        'original_name' => 'draft-video.webm',
        'mime_type' => 'video/webm',
        'size' => 100,
        'position' => 0,
    ]);

    $this->getJson('/api/mobile/discovery/media')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->getJson("/api/mobile/discovery/media/{$video->id}")
        ->assertNotFound();
});
