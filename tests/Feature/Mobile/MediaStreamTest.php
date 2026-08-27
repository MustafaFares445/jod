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

test('mobile media video stream returns the complete video with range support headers', function (): void {
    [$video, $contents] = media_stream_test_video();

    $response = $this->get("/api/mobile/discovery/media/{$video->id}/stream");

    $response
        ->assertOk()
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertHeader('Content-Length', (string) strlen($contents));

    expect($response->streamedContent())->toBe($contents);
});

test('mobile media video stream supports partial byte range requests', function (): void {
    [$video] = media_stream_test_video();

    $response = $this
        ->withHeader('Range', 'bytes=2-5')
        ->get("/api/mobile/discovery/media/{$video->id}/stream");

    $response
        ->assertStatus(206)
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Range', 'bytes 2-5/10')
        ->assertHeader('Content-Length', '4');

    expect($response->streamedContent())->toBe('2345');
});

test('mobile media video stream supports open ended and suffix byte ranges', function (): void {
    [$video] = media_stream_test_video();

    $openEnded = $this
        ->withHeader('Range', 'bytes=7-')
        ->get("/api/mobile/discovery/media/{$video->id}/stream");

    $openEnded
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 7-9/10')
        ->assertHeader('Content-Length', '3');

    expect($openEnded->streamedContent())->toBe('789');

    $suffix = $this
        ->withHeader('Range', 'bytes=-3')
        ->get("/api/mobile/discovery/media/{$video->id}/stream");

    $suffix
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 7-9/10')
        ->assertHeader('Content-Length', '3');

    expect($suffix->streamedContent())->toBe('789');
});

test('mobile media video stream rejects unsatisfiable byte ranges', function (): void {
    [$video] = media_stream_test_video();

    $this
        ->withHeader('Range', 'bytes=20-30')
        ->get("/api/mobile/discovery/media/{$video->id}/stream")
        ->assertStatus(416)
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Range', 'bytes */10');
});

test('mobile media video stream only exposes videos from active organizations', function (): void {
    [$video] = media_stream_test_video('inactive');

    $this->getJson("/api/mobile/discovery/media/{$video->id}/stream")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

/**
 * @return array{0: Media, 1: string}
 */
function media_stream_test_video(string $organizationStatus = 'active'): array
{
    $organization = Organization::query()->create([
        'name' => 'Streaming Organization',
        'email' => 'streaming-'.uniqid().'@example.test',
        'status' => $organizationStatus,
        'verification_status' => 'verified',
    ]);

    $contents = '0123456789';
    $path = "organizations/{$organization->id}/video.mp4";
    Storage::disk('public')->put($path, $contents);

    $video = Media::query()->create([
        'model_type' => MediaModel::ORGANIZATION->value,
        'model_id' => $organization->id,
        'prop' => 'videos',
        'disk' => 'public',
        'path' => $path,
        'original_name' => 'video.mp4',
        'mime_type' => 'video/mp4',
        'size' => strlen($contents),
        'position' => 0,
    ]);

    return [$video, $contents];
}
