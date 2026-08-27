<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaModel;
use App\Jobs\GenerateVideoPreview;
use App\Models\Media;
use App\Models\MediaUpload;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrganizationVideoUploadService
{
    public const CHUNK_SIZE = 5 * 1024 * 1024;

    public const MAX_FILE_SIZE = 100 * 1024 * 1024;

    public const MAX_VIDEOS = 10;

    public const SESSION_TTL_HOURS = 24;

    public const ALLOWED_MIME_TYPES = ['video/mp4', 'video/quicktime', 'video/webm'];

    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * @param array{originalName:string,mimeType:string,totalSize:int,replaceVideoId?:string|null} $data
     */
    public function initiate(Organization $organization, ?User $user, array $data): MediaUpload
    {
        $replaceVideoId = filled($data['replaceVideoId'] ?? null) ? (string) $data['replaceVideoId'] : null;

        if ($replaceVideoId !== null) {
            $this->video($organization, $replaceVideoId);
        } else {
            $this->assertCapacity($organization);
        }

        $totalSize = (int) $data['totalSize'];
        $totalChunks = (int) ceil($totalSize / self::CHUNK_SIZE);

        return MediaUpload::query()->create([
            'organization_id' => $organization->id,
            'uploaded_by' => $user?->id,
            'replace_media_id' => $replaceVideoId,
            'original_name' => $data['originalName'],
            'mime_type' => $data['mimeType'],
            'total_size' => $totalSize,
            'chunk_size' => self::CHUNK_SIZE,
            'total_chunks' => $totalChunks,
            'received_chunks' => [],
            'uploaded_bytes' => 0,
            'status' => 'initiated',
            'expires_at' => now()->addHours(self::SESSION_TTL_HOURS),
        ]);
    }

    public function findForOrganization(Organization $organization, string $uploadId): MediaUpload
    {
        return MediaUpload::query()
            ->whereKey($uploadId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();
    }

    public function storeChunkStream(MediaUpload $upload, int $chunkIndex, mixed $stream): MediaUpload
    {
        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['chunk' => ['A binary chunk body is required.']]);
        }

        return DB::transaction(function () use ($upload, $chunkIndex, $stream): MediaUpload {
            /** @var MediaUpload $locked */
            $locked = MediaUpload::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();
            $this->assertChunkWritable($locked);

            if ($chunkIndex < 0 || $chunkIndex >= $locked->total_chunks) {
                throw ValidationException::withMessages(['chunk' => ['Chunk index is outside the upload range.']]);
            }

            $received = collect($locked->received_chunks ?? [])->map(static fn ($value): int => (int) $value)->all();
            if (in_array($chunkIndex, $received, true)) {
                return $locked;
            }

            $path = $this->chunkPath($locked, $chunkIndex);
            Storage::disk('local')->makeDirectory($this->chunkDirectory($locked));
            Storage::disk('local')->writeStream($path, $stream);

            $actualSize = Storage::disk('local')->size($path);
            $expectedSize = $this->expectedChunkSize($locked, $chunkIndex);

            if ($actualSize !== $expectedSize) {
                Storage::disk('local')->delete($path);

                throw ValidationException::withMessages([
                    'chunk' => ["Chunk {$chunkIndex} has {$actualSize} bytes; expected {$expectedSize} bytes."],
                ]);
            }

            $received[] = $chunkIndex;
            sort($received);

            $locked->update([
                'received_chunks' => array_values(array_unique($received)),
                'uploaded_bytes' => min($locked->total_size, $locked->uploaded_bytes + $actualSize),
                'status' => 'uploading',
                'expires_at' => now()->addHours(self::SESSION_TTL_HOURS),
            ]);

            return $locked->refresh();
        });
    }

    public function pause(MediaUpload $upload): MediaUpload
    {
        $this->assertNotExpired($upload);

        if (! in_array($upload->status, ['initiated', 'uploading'], true)) {
            throw ValidationException::withMessages(['upload' => ['Only an active upload can be paused.']]);
        }

        $upload->update(['status' => 'paused']);

        return $upload->refresh();
    }

    public function resume(MediaUpload $upload): MediaUpload
    {
        $this->assertNotExpired($upload);

        if ($upload->status !== 'paused') {
            throw ValidationException::withMessages(['upload' => ['Only a paused upload can be resumed.']]);
        }

        $upload->update([
            'status' => 'uploading',
            'expires_at' => now()->addHours(self::SESSION_TTL_HOURS),
        ]);

        return $upload->refresh();
    }

    /** @return array{upload:MediaUpload,video:Media} */
    public function complete(MediaUpload $upload): array
    {
        /** @var MediaUpload $locked */
        $locked = DB::transaction(function () use ($upload): MediaUpload {
            /** @var MediaUpload $session */
            $session = MediaUpload::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();
            $this->assertChunkWritable($session);

            $received = collect($session->received_chunks ?? [])
                ->map(static fn ($value): int => (int) $value)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $expected = range(0, $session->total_chunks - 1);
            $missing = array_values(array_diff($expected, $received));

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'upload' => ['Upload is incomplete. Missing chunks: '.implode(', ', $missing).'.'],
                ]);
            }

            $session->update(['status' => 'assembling']);

            return $session->refresh();
        });

        $assembledRelativePath = "media-uploads/{$locked->id}/assembled-video";
        $disk = Storage::disk('local');
        $disk->makeDirectory("media-uploads/{$locked->id}");
        $assembledPath = $disk->path($assembledRelativePath);
        $output = fopen($assembledPath, 'wb');

        if ($output === false) {
            $this->returnToUploading($locked);
            throw ValidationException::withMessages(['upload' => ['Unable to assemble the uploaded video.']]);
        }

        try {
            for ($index = 0; $index < $locked->total_chunks; $index++) {
                $input = $disk->readStream($this->chunkPath($locked, $index));
                if ($input === false) {
                    throw ValidationException::withMessages(['upload' => ["Chunk {$index} is missing from storage."]]);
                }

                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } catch (\Throwable $exception) {
            fclose($output);
            $this->returnToUploading($locked);
            throw $exception;
        }

        fclose($output);

        if ($disk->size($assembledRelativePath) !== $locked->total_size) {
            $disk->delete($assembledRelativePath);
            $this->returnToUploading($locked);
            throw ValidationException::withMessages(['upload' => ['Assembled video size does not match the initiated upload.']]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualMimeType = $finfo->file($assembledPath) ?: '';

        if (! in_array($actualMimeType, self::ALLOWED_MIME_TYPES, true)) {
            $disk->delete($assembledRelativePath);
            $this->returnToUploading($locked);
            throw ValidationException::withMessages([
                'upload' => ['The assembled file is not a supported MP4, MOV, or WebM video.'],
            ]);
        }

        try {
            $file = new UploadedFile(
                $assembledPath,
                $locked->original_name,
                $actualMimeType,
                null,
                true,
            );

            $organization = Organization::query()->findOrFail($locked->organization_id);

            if ($locked->replace_media_id !== null) {
                $this->video($organization, (string) $locked->replace_media_id);
                $video = $this->mediaService->replace(
                    MediaModel::ORGANIZATION,
                    (string) $organization->id,
                    'videos',
                    (string) $locked->replace_media_id,
                    $file,
                );
            } else {
                $video = $this->mediaService->upload(
                    MediaModel::ORGANIZATION,
                    (string) $organization->id,
                    'videos',
                    $file,
                );
            }

            $locked->update([
                'media_id' => $video->id,
                'uploaded_bytes' => $locked->total_size,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            if ($video->preview_status === 'pending') {
                GenerateVideoPreview::dispatch((string) $video->id, (string) $video->path);
            }

            $disk->deleteDirectory("media-uploads/{$locked->id}");

            return ['upload' => $locked->refresh(), 'video' => $video];
        } catch (\Throwable $exception) {
            $this->returnToUploading($locked);
            throw $exception;
        }
    }

    public function cancel(MediaUpload $upload): void
    {
        if ($upload->status === 'completed') {
            throw ValidationException::withMessages(['upload' => ['A completed upload cannot be cancelled.']]);
        }

        if ($upload->status === 'assembling') {
            throw ValidationException::withMessages(['upload' => ['An upload cannot be cancelled while it is being assembled.']]);
        }

        Storage::disk('local')->deleteDirectory("media-uploads/{$upload->id}");
        $upload->update(['status' => 'cancelled']);
    }

    private function assertCapacity(Organization $organization): void
    {
        $videoCount = Media::query()
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('model_id', $organization->id)
            ->where('prop', 'videos')
            ->count();

        $pendingNewUploads = MediaUpload::query()
            ->where('organization_id', $organization->id)
            ->whereNull('replace_media_id')
            ->whereIn('status', ['initiated', 'uploading', 'paused', 'assembling'])
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if (($videoCount + $pendingNewUploads) >= self::MAX_VIDEOS) {
            throw ValidationException::withMessages([
                'video' => ['The organization can have at most '.self::MAX_VIDEOS.' videos including active uploads.'],
            ]);
        }
    }

    private function video(Organization $organization, string $videoId): Media
    {
        return Media::query()
            ->whereKey($videoId)
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('model_id', $organization->id)
            ->where('prop', 'videos')
            ->firstOrFail();
    }

    private function assertChunkWritable(MediaUpload $upload): void
    {
        $this->assertNotExpired($upload);

        if ($upload->status === 'paused') {
            throw ValidationException::withMessages(['upload' => ['Upload is paused. Resume it before sending more chunks.']]);
        }

        if (! in_array($upload->status, ['initiated', 'uploading'], true)) {
            throw ValidationException::withMessages(['upload' => ["Upload cannot accept chunks while status is {$upload->status}."]]);
        }
    }

    private function assertNotExpired(MediaUpload $upload): void
    {
        if ($upload->expires_at !== null && $upload->expires_at->isPast()) {
            throw ValidationException::withMessages(['upload' => ['Upload session has expired. Start a new upload.']]);
        }
    }

    private function expectedChunkSize(MediaUpload $upload, int $chunkIndex): int
    {
        if ($chunkIndex < ($upload->total_chunks - 1)) {
            return $upload->chunk_size;
        }

        return $upload->total_size - ($upload->chunk_size * ($upload->total_chunks - 1));
    }

    private function chunkDirectory(MediaUpload $upload): string
    {
        return "media-uploads/{$upload->id}/chunks";
    }

    private function chunkPath(MediaUpload $upload, int $chunkIndex): string
    {
        return $this->chunkDirectory($upload)."/{$chunkIndex}.part";
    }

    private function returnToUploading(MediaUpload $upload): void
    {
        MediaUpload::query()
            ->whereKey($upload->id)
            ->where('status', 'assembling')
            ->update(['status' => 'uploading']);
    }
}
