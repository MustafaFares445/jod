<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Media;
use App\Services\VideoPreviewGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateVideoPreview implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public function __construct(
        public readonly string $mediaId,
        public readonly string $sourcePath,
    ) {}

    public function uniqueId(): string
    {
        return $this->mediaId.':'.sha1($this->sourcePath);
    }

    public function handle(VideoPreviewGenerator $generator): void
    {
        $media = Media::query()->find($this->mediaId);

        if ($media === null || $media->path !== $this->sourcePath) {
            return;
        }

        $generator->generate($media, $this->sourcePath);
    }

    public function failed(?Throwable $exception): void
    {
        Media::query()
            ->whereKey($this->mediaId)
            ->where('path', $this->sourcePath)
            ->update([
                'preview_status' => 'failed',
                'preview_error' => mb_substr(
                    $exception?->getMessage() ?? 'Video preview generation failed.',
                    0,
                    4000,
                ),
            ]);
    }
}
