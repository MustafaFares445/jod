<?php

declare(strict_types=1);

use App\Enums\MediaModel;
use App\Jobs\GenerateVideoPreview;
use App\Models\Media;
use App\Models\Organization;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
    config(['video.preview.enabled' => true]);
});

test('video media queues preview generation when created', function (): void {
    $organization = video_preview_test_organization();

    $video = app(MediaService::class)->upload(
        MediaModel::ORGANIZATION,
        (string) $organization->id,
        'videos',
        UploadedFile::fake()->create('video.mp4', 100, 'video/mp4'),
    );

    expect($video->preview_status)->toBe('pending');

    Queue::assertPushed(
        GenerateVideoPreview::class,
        fn (GenerateVideoPreview $job): bool => $job->mediaId === $video->id
            && $job->sourcePath === $video->path,
    );
});

test('replacing a video removes the stale preview and queues a new preview', function (): void {
    $organization = video_preview_test_organization();
    $service = app(MediaService::class);

    $video = $service->upload(
        MediaModel::ORGANIZATION,
        (string) $organization->id,
        'videos',
        UploadedFile::fake()->create('before.mp4', 100, 'video/mp4'),
    );

    $oldOriginalPath = $video->path;
    $oldPreviewPath = "media/organization/{$organization->id}/videos/previews/old-preview.mp4";
    Storage::disk('public')->put($oldPreviewPath, 'old-preview');

    $video->update([
        'preview_disk' => 'public',
        'preview_path' => $oldPreviewPath,
        'preview_mime_type' => 'video/mp4',
        'preview_size' => 11,
        'preview_status' => 'ready',
    ]);

    $replacement = $service->replace(
        MediaModel::ORGANIZATION,
        (string) $organization->id,
        'videos',
        (string) $video->id,
        UploadedFile::fake()->create('after.mp4', 120, 'video/mp4'),
    );

    expect($replacement->path)->not->toBe($oldOriginalPath)
        ->and($replacement->preview_status)->toBe('pending')
        ->and($replacement->preview_disk)->toBeNull()
        ->and($replacement->preview_path)->toBeNull()
        ->and($replacement->preview_size)->toBeNull();

    Storage::disk('public')->assertMissing($oldOriginalPath);
    Storage::disk('public')->assertMissing($oldPreviewPath);
    Storage::disk('public')->assertExists($replacement->path);

    Queue::assertPushed(
        GenerateVideoPreview::class,
        fn (GenerateVideoPreview $job): bool => $job->mediaId === $replacement->id
            && $job->sourcePath === $replacement->path,
    );
});

test('deleting a video deletes its generated preview too', function (): void {
    $organization = video_preview_test_organization();
    $service = app(MediaService::class);

    $video = $service->upload(
        MediaModel::ORGANIZATION,
        (string) $organization->id,
        'videos',
        UploadedFile::fake()->create('delete.mp4', 100, 'video/mp4'),
    );

    $originalPath = $video->path;
    $previewPath = "media/organization/{$organization->id}/videos/previews/delete-preview.mp4";
    Storage::disk('public')->put($previewPath, 'preview');

    $video->update([
        'preview_disk' => 'public',
        'preview_path' => $previewPath,
        'preview_mime_type' => 'video/mp4',
        'preview_size' => 7,
        'preview_status' => 'ready',
    ]);

    $service->delete(
        MediaModel::ORGANIZATION,
        (string) $organization->id,
        'videos',
        (string) $video->id,
    );

    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertMissing($previewPath);
    $this->assertDatabaseMissing('media', ['id' => $video->id]);
});

test('preview generation can be disabled without blocking video upload', function (): void {
    config(['video.preview.enabled' => false]);
    $organization = video_preview_test_organization();

    $video = app(MediaService::class)->upload(
        MediaModel::ORGANIZATION,
        (string) $organization->id,
        'videos',
        UploadedFile::fake()->create('disabled.mp4', 100, 'video/mp4'),
    );

    expect($video->preview_status)->toBe('disabled');
    Queue::assertNothingPushed();
});

test('existing videos without previews can be queued for backfill', function (): void {
    $organization = video_preview_test_organization();

    $missing = Media::query()->create([
        'model_type' => MediaModel::ORGANIZATION->value,
        'model_id' => $organization->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => 'videos/missing.mp4',
        'original_name' => 'missing.mp4',
        'mime_type' => 'video/mp4',
        'size' => 100,
        'position' => 0,
        'preview_status' => null,
    ]);

    Media::query()->create([
        'model_type' => MediaModel::ORGANIZATION->value,
        'model_id' => $organization->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => 'videos/ready.mp4',
        'original_name' => 'ready.mp4',
        'mime_type' => 'video/mp4',
        'size' => 100,
        'position' => 1,
        'preview_disk' => 'public',
        'preview_path' => 'videos/ready-preview.mp4',
        'preview_mime_type' => 'video/mp4',
        'preview_size' => 50,
        'preview_status' => 'ready',
    ]);

    Artisan::call('videos:generate-previews');

    expect($missing->refresh()->preview_status)->toBe('pending');

    Queue::assertPushed(GenerateVideoPreview::class, 1);
    Queue::assertPushed(
        GenerateVideoPreview::class,
        fn (GenerateVideoPreview $job): bool => $job->mediaId === $missing->id
            && $job->sourcePath === $missing->path,
    );
});

test('preview job records a final failure against the matching video version', function (): void {
    $organization = video_preview_test_organization();

    $video = Media::query()->create([
        'model_type' => MediaModel::ORGANIZATION->value,
        'model_id' => $organization->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => 'videos/failing.mp4',
        'original_name' => 'failing.mp4',
        'mime_type' => 'video/mp4',
        'size' => 100,
        'position' => 0,
        'preview_status' => 'processing',
    ]);

    (new GenerateVideoPreview((string) $video->id, (string) $video->path))
        ->failed(new RuntimeException('ffmpeg is not installed'));

    $video->refresh();

    expect($video->preview_status)->toBe('failed')
        ->and($video->preview_error)->toContain('ffmpeg is not installed');
});

function video_preview_test_organization(): Organization
{
    return Organization::query()->create([
        'name' => 'Video Preview Organization',
        'email' => 'video-preview-'.uniqid().'@example.test',
        'status' => 'active',
        'verification_status' => 'verified',
    ]);
}
