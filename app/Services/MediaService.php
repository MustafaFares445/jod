<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaModel;
use App\Jobs\GenerateVideoPreview;
use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaService
{
    public function resolveTarget(MediaModel $model, string $modelId): Model
    {
        $modelClass = $model->modelClass();

        return $modelClass::query()->findOrFail($modelId);
    }

    public function assertProp(MediaModel $model, string $prop): int
    {
        $maxItems = $model->maxItems($prop);

        if ($maxItems === null) {
            throw ValidationException::withMessages([
                'prop' => ["Prop [{$prop}] is not supported for model [{$model->value}]."],
            ]);
        }

        return $maxItems;
    }

    public function upload(MediaModel $model, string $modelId, string $prop, UploadedFile $file): Media
    {
        $maxItems = $this->assertProp($model, $prop);
        $query = $this->query($model, $modelId, $prop);
        $count = $query->count();

        if ($count >= $maxItems) {
            throw ValidationException::withMessages([
                'file' => ["The [{$prop}] media limit is {$maxItems}. Replace or delete an existing media item first."],
            ]);
        }

        $path = $file->store($this->directory($model, $modelId, $prop), 'public');

        try {
            $media = Media::query()->create([
                'model_type' => $model,
                'model_id' => $modelId,
                'prop' => $prop,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'position' => $count,
                'preview_status' => $this->initialPreviewStatus($prop),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        $this->queueVideoPreview($media);

        return $media;
    }

    public function replace(MediaModel $model, string $modelId, string $prop, string $mediaId, UploadedFile $file): Media
    {
        $this->assertProp($model, $prop);
        $media = $this->findScoped($model, $modelId, $prop, $mediaId);
        $newPath = $file->store($this->directory($model, $modelId, $prop), 'public');
        $oldDisk = $media->disk;
        $oldPath = $media->path;
        $oldPreviewDisk = $media->preview_disk;
        $oldPreviewPath = $media->preview_path;

        try {
            $media->update([
                'disk' => 'public',
                'path' => $newPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'preview_disk' => null,
                'preview_path' => null,
                'preview_mime_type' => null,
                'preview_size' => null,
                'preview_status' => $this->initialPreviewStatus($prop),
                'preview_error' => null,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        Storage::disk($oldDisk)->delete($oldPath);

        if (filled($oldPreviewDisk) && filled($oldPreviewPath)) {
            Storage::disk((string) $oldPreviewDisk)->delete((string) $oldPreviewPath);
        }

        $media = $media->refresh();
        $this->queueVideoPreview($media);

        return $media;
    }

    public function delete(MediaModel $model, string $modelId, string $prop, string $mediaId): void
    {
        $this->assertProp($model, $prop);
        $media = $this->findScoped($model, $modelId, $prop, $mediaId);
        $disk = $media->disk;
        $path = $media->path;
        $previewDisk = $media->preview_disk;
        $previewPath = $media->preview_path;

        DB::transaction(function () use ($media, $model, $modelId, $prop): void {
            $media->delete();
            $this->resequence($model, $modelId, $prop);
        });

        Storage::disk($disk)->delete($path);

        if (filled($previewDisk) && filled($previewPath)) {
            Storage::disk((string) $previewDisk)->delete((string) $previewPath);
        }
    }

    /** @return Collection<int, Media> */
    public function forTarget(MediaModel $model, string $modelId, ?string $prop = null): Collection
    {
        $query = Media::query()
            ->where('model_type', $model->value)
            ->where('model_id', $modelId)
            ->orderBy('prop')
            ->orderBy('position');

        if ($prop !== null) {
            $query->where('prop', $prop);
        }

        return $query->get();
    }

    private function findScoped(MediaModel $model, string $modelId, string $prop, string $mediaId): Media
    {
        return $this->query($model, $modelId, $prop)->whereKey($mediaId)->firstOrFail();
    }

    /** @return Builder<Media> */
    private function query(MediaModel $model, string $modelId, string $prop): Builder
    {
        return Media::query()
            ->where('model_type', $model->value)
            ->where('model_id', $modelId)
            ->where('prop', $prop);
    }

    private function resequence(MediaModel $model, string $modelId, string $prop): void
    {
        $this->query($model, $modelId, $prop)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->values()
            ->each(static fn (Media $item, int $position) => $item->update(['position' => $position]));
    }

    private function directory(MediaModel $model, string $modelId, string $prop): string
    {
        return "media/{$model->value}/{$modelId}/{$prop}";
    }

    private function initialPreviewStatus(string $prop): ?string
    {
        if ($prop !== 'videos') {
            return null;
        }

        return config('video.preview.enabled', true) ? 'pending' : 'disabled';
    }

    private function queueVideoPreview(Media $media): void
    {
        if ($media->prop !== 'videos' || $media->preview_status !== 'pending') {
            return;
        }

        GenerateVideoPreview::dispatch((string) $media->id, (string) $media->path);
    }
}
