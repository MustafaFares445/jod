<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaModel;
use App\Models\Media;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VideoPreviewGenerator
{
    public function generate(Media $media, string $expectedSourcePath): void
    {
        if (! $this->isOrganizationVideo($media) || $media->path !== $expectedSourcePath) {
            return;
        }

        if (! config('video.preview.enabled', true)) {
            $media->update([
                'preview_status' => 'disabled',
                'preview_error' => null,
            ]);

            return;
        }

        $media->update([
            'preview_status' => 'processing',
            'preview_error' => null,
        ]);

        $workingDirectory = 'video-previews/'.Str::uuid();
        $localDisk = Storage::disk('local');
        $localDisk->makeDirectory($workingDirectory);

        $inputRelativePath = $workingDirectory.'/source-video';
        $outputRelativePath = $workingDirectory.'/preview.mp4';
        $inputPath = $localDisk->path($inputRelativePath);
        $outputPath = $localDisk->path($outputRelativePath);

        try {
            $this->copyOriginalToLocal($media, $inputRelativePath);

            $result = Process::timeout((int) config('video.preview.process_timeout_seconds', 45))
                ->run($this->command($inputPath, $outputPath));

            if (! $result->successful()) {
                throw new RuntimeException(
                    'FFmpeg failed to generate video preview: '.trim($result->errorOutput() ?: $result->output()),
                );
            }

            if (! is_file($outputPath) || filesize($outputPath) === false || filesize($outputPath) <= 0) {
                throw new RuntimeException('FFmpeg completed without producing a valid preview file.');
            }

            $current = Media::query()->find($media->id);

            if ($current === null || $current->path !== $expectedSourcePath) {
                return;
            }

            $previewDisk = 'public';
            $previewPath = self::previewPathFor(
                (string) $current->model_id,
                (string) $current->id,
                $expectedSourcePath,
            );
            $output = fopen($outputPath, 'rb');

            if ($output === false) {
                throw new RuntimeException('Unable to open generated preview for storage.');
            }

            try {
                Storage::disk($previewDisk)->writeStream($previewPath, $output);
            } finally {
                fclose($output);
            }

            $storedSize = (int) Storage::disk($previewDisk)->size($previewPath);
            $oldPreviewDisk = $current->preview_disk;
            $oldPreviewPath = $current->preview_path;

            $updated = Media::query()
                ->whereKey($media->id)
                ->where('path', $expectedSourcePath)
                ->update([
                    'preview_disk' => $previewDisk,
                    'preview_path' => $previewPath,
                    'preview_mime_type' => 'video/mp4',
                    'preview_size' => $storedSize,
                    'preview_status' => 'ready',
                    'preview_error' => null,
                ]);

            if ($updated === 0) {
                Storage::disk($previewDisk)->delete($previewPath);

                return;
            }

            if (
                filled($oldPreviewDisk)
                && filled($oldPreviewPath)
                && ($oldPreviewDisk !== $previewDisk || $oldPreviewPath !== $previewPath)
            ) {
                Storage::disk((string) $oldPreviewDisk)->delete((string) $oldPreviewPath);
            }
        } finally {
            $localDisk->deleteDirectory($workingDirectory);
        }
    }

    private function copyOriginalToLocal(Media $media, string $targetRelativePath): void
    {
        $source = Storage::disk($media->disk)->readStream($media->path);

        if ($source === false) {
            throw new RuntimeException('Unable to read the original video for preview generation.');
        }

        try {
            Storage::disk('local')->writeStream($targetRelativePath, $source);
        } finally {
            fclose($source);
        }
    }

    private function command(string $inputPath, string $outputPath): string
    {
        $binary = (string) config('video.ffmpeg_binary', 'ffmpeg');
        $duration = (int) config('video.preview.duration_seconds', 3);
        $height = (int) config('video.preview.height', 480);
        $crf = (int) config('video.preview.crf', 28);
        $preset = (string) config('video.preview.preset', 'veryfast');

        return implode(' ', [
            escapeshellarg($binary),
            '-hide_banner',
            '-loglevel error',
            '-y',
            '-i '.escapeshellarg($inputPath),
            '-t '.escapeshellarg((string) $duration),
            '-an',
            '-vf '.escapeshellarg("scale=-2:{$height}"),
            '-c:v libx264',
            '-preset '.escapeshellarg($preset),
            '-crf '.escapeshellarg((string) $crf),
            '-pix_fmt yuv420p',
            '-movflags +faststart',
            escapeshellarg($outputPath),
        ]);
    }

    public static function previewPathFor(string $modelId, string $mediaId, string $sourcePath): string
    {
        $version = substr(sha1($sourcePath), 0, 16);

        return "media/organization/{$modelId}/videos/previews/{$mediaId}-{$version}.mp4";
    }

    private function isOrganizationVideo(Media $media): bool
    {
        $modelType = $media->model_type instanceof MediaModel
            ? $media->model_type->value
            : (string) $media->model_type;

        return $modelType === MediaModel::ORGANIZATION->value && $media->prop === 'videos';
    }
}
