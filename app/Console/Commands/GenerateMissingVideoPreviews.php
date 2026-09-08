<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MediaModel;
use App\Jobs\GenerateVideoPreview;
use App\Models\Media;
use App\Services\VideoPreviewGenerator;
use Illuminate\Console\Command;

class GenerateMissingVideoPreviews extends Command
{
    protected $signature = 'videos:generate-previews {--force : Regenerate previews that are already ready}';

    protected $description = 'Queue preview generation for public organization and post videos.';

    public function handle(VideoPreviewGenerator $generator): int
    {
        if (! config('video.preview.enabled', true)) {
            $this->components->warn('Video preview generation is disabled by configuration.');

            return self::SUCCESS;
        }

        if (! $generator->isFfmpegAvailable()) {
            Media::query()
                ->whereIn('model_type', [MediaModel::ORGANIZATION->value, MediaModel::POST->value])
                ->where('prop', 'videos')
                ->where(function ($query): void {
                    $query->whereNull('preview_status')
                        ->orWhereIn('preview_status', ['pending', 'processing', 'failed']);
                })
                ->update([
                    'preview_status' => 'disabled',
                    'preview_error' => null,
                ]);

            $this->components->warn('FFmpeg is not available. Video previews were disabled; original videos remain available.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $queued = 0;

        Media::query()
            ->whereIn('model_type', [MediaModel::ORGANIZATION->value, MediaModel::POST->value])
            ->where('prop', 'videos')
            ->when(! $force, fn ($query) => $query->where(function ($inner): void {
                $inner->whereNull('preview_status')
                    ->orWhereIn('preview_status', ['pending', 'failed', 'disabled']);
            }))
            ->chunkById(100, function ($videos) use (&$queued): void {
                foreach ($videos as $video) {
                    $video->update([
                        'preview_status' => 'pending',
                        'preview_error' => null,
                    ]);

                    GenerateVideoPreview::dispatch((string) $video->id, (string) $video->path);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} video preview job(s).");

        return self::SUCCESS;
    }
}
