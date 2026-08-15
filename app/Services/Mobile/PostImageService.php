<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PostImageService
{
    public const MAX_IMAGES = 5;

    /**
     * @param  list<UploadedFile>  $files
     */
    public function add(Post $post, array $files): Post
    {
        if ($files === []) {
            return $post->load('images');
        }

        /** @var list<array{disk: string, path: string}> $storedFiles */
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($post, $files, &$storedFiles): Post {
                $lockedPost = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
                $currentCount = $lockedPost->images()->count();

                if ($currentCount + count($files) > self::MAX_IMAGES) {
                    throw ValidationException::withMessages([
                        'images' => ['A post may contain at most 5 images.'],
                    ]);
                }

                $position = (int) ($lockedPost->images()->max('position') ?? -1) + 1;

                foreach ($files as $file) {
                    $extension = $file->extension() ?: $file->getClientOriginalExtension();
                    $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
                    $path = $file->storeAs("mobile/posts/{$lockedPost->id}", $filename, 'public');

                    if ($path === false) {
                        throw new RuntimeException('Unable to store post image.');
                    }

                    $storedFiles[] = ['disk' => 'public', 'path' => $path];

                    $lockedPost->images()->create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => (int) ($file->getSize() ?: 0),
                        'position' => $position++,
                    ]);
                }

                return $lockedPost->load('images');
            });
        } catch (Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                Storage::disk($storedFile['disk'])->delete($storedFile['path']);
            }

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $imageIds
     */
    public function reorder(Post $post, array $imageIds): Post
    {
        return DB::transaction(function () use ($post, $imageIds): Post {
            $lockedPost = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $images = $lockedPost->images()->orderBy('position')->orderBy('id')->get();
            $currentIds = $images->pluck('id')->map(static fn ($id): string => (string) $id)->all();

            if (count($imageIds) !== count($currentIds)
                || array_diff($imageIds, $currentIds) !== []
                || array_diff($currentIds, $imageIds) !== []) {
                throw ValidationException::withMessages([
                    'imageIds' => ['The image order must contain every image belonging to this post exactly once.'],
                ]);
            }

            foreach ($imageIds as $position => $imageId) {
                PostImage::query()
                    ->where('post_id', $lockedPost->id)
                    ->whereKey($imageId)
                    ->update(['position' => $position]);
            }

            return $lockedPost->load('images');
        });
    }

    public function delete(Post $post, string $imageId): ?Post
    {
        $image = $post->images()->whereKey($imageId)->first();

        if ($image === null) {
            return null;
        }

        DB::transaction(function () use ($post, $image): void {
            $image->delete();

            $post->images()
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->values()
                ->each(static function (PostImage $postImage, int $position): void {
                    if ((int) $postImage->position !== $position) {
                        $postImage->update(['position' => $position]);
                    }
                });
        });

        Storage::disk($image->disk)->delete($image->path);

        return $post->refresh()->load('images');
    }

    public function purge(Post $post): void
    {
        $images = $post->images()->get();

        foreach ($images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }

        $post->images()->delete();
    }
}
