<?php

declare(strict_types=1);

use App\Enums\MediaModel;
use App\Models\Media;
use App\Models\Organization;
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
        ->assertJsonPath('data.0.organization.logo.id', $logo->id)
        ->assertJsonPath('data.0.organization.logo.model', 'organization')
        ->assertJsonPath('data.0.organization.logo.prop', 'logo');

    $this->getJson("/api/mobile/discovery/media/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.streamUrl', route('mobile.discovery.media.stream', ['video' => $video->id]))
        ->assertJsonPath('data.previewUrl', $previewUrl)
        ->assertJsonPath('data.previewStatus', 'ready')
        ->assertJsonPath('data.organization.id', $organization->id)
        ->assertJsonPath('data.organization.logo.id', $logo->id)
        ->assertJsonPath('data.organization.logo.prop', 'logo');
});
