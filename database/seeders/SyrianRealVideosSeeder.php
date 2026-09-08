<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Jobs\GenerateVideoPreview;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SyrianRealVideosSeeder extends Seeder
{
    private const MAX_VIDEO_BYTES = 100 * 1024 * 1024;

    /** @var array<string, string> */
    private array $categoryIds = [];

    public function run(): void
    {
        if (! Schema::hasTable('posts') || ! Schema::hasTable('media')) {
            return;
        }

        $data = json_decode(
            (string) file_get_contents(database_path('data/syrian_real_videos.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->categoryIds = $this->loadCategoryIds();
        $authorId = $this->resolveAdminAuthorId();

        if ($authorId === null) {
            $this->command?->warn('Skipping Syrian real videos: no user is available as the post author.');
            return;
        }

        foreach ($data['videos'] as $index => $video) {
            $this->seedVideoPost($video, $index, $authorId);
        }
    }

    private function seedVideoPost(array $video, int $index, string $authorId): void
    {
        $categoryId = $this->categoryIds[$video['category']] ?? null;

        if ($categoryId === null) {
            throw new \RuntimeException('Unknown category for Syrian video seed: '.$video['category']);
        }

        $postId = $this->id('post:'.$video['key']);
        $publishedAt = now()->subDays((int) ($video['seed_days_ago'] ?? ($index + 1)));

        DB::table('posts')->updateOrInsert(
            ['id' => $postId],
            $this->columns('posts', [
                'id' => $postId,
                'title' => $video['title'],
                'summary' => $video['summary'],
                'content' => trim((string) $video['content']),
                'type' => $video['type'] ?? 'general',
                'audience' => $video['audience'] ?? 'general',
                'status' => 'published',
                'location' => $video['location'] ?? 'سوريا',
                'organization_id' => null,
                'group_id' => null,
                'campaign_id' => null,
                'category_id' => $categoryId,
                'author_id' => $authorId,
                'views_count' => 420 + ($index * 173),
                'reactions_count' => 31 + ($index * 17),
                'applications_count' => 0,
                'published_at' => $publishedAt,
                'submitted_at' => $publishedAt,
                'deleted_at' => null,
                'created_at' => $publishedAt,
                'updated_at' => now(),
            ]),
        );

        $this->seedVideoMedia($video, $postId, $index);
    }

    private function seedVideoMedia(array $video, string $postId, int $index): void
    {
        $disk = Storage::disk('public');
        $path = 'content/syria/videos/'.$video['key'].'.webm';
        $stored = $disk->exists($path) && $disk->size($path) > 1024;

        if (! $stored) {
            $stored = $this->downloadVideo($video, $path);
        }

        if (! $stored || ! $disk->exists($path)) {
            $this->command?->warn('Real video unavailable; post was seeded without video media: '.$video['key']);
            return;
        }

        $size = (int) $disk->size($path);
        if ($size <= 0 || $size > self::MAX_VIDEO_BYTES) {
            $disk->delete($path);
            $this->command?->warn('Real video rejected because the stored size is invalid: '.$video['key']);
            return;
        }

        $mediaId = $this->id('media:'.$video['key']);
        $previewStatus = config('video.preview.enabled', true) ? 'pending' : 'disabled';
        $existing = DB::table('media')->where('id', $mediaId)->first();

        DB::table('media')->updateOrInsert(
            ['id' => $mediaId],
            $this->columns('media', [
                'id' => $mediaId,
                'model_type' => 'post',
                'model_id' => $postId,
                'post_id' => $postId,
                'prop' => 'videos',
                'disk' => 'public',
                'path' => $path,
                'original_name' => basename((string) $video['file_name']),
                'description' => trim((string) $video['summary']),
                'mime_type' => 'video/webm',
                'size' => $size,
                'position' => $index,
                'preview_status' => $existing?->preview_status === 'ready' ? 'ready' : $previewStatus,
                'preview_disk' => $existing?->preview_status === 'ready' ? $existing->preview_disk : null,
                'preview_path' => $existing?->preview_status === 'ready' ? $existing->preview_path : null,
                'preview_mime_type' => $existing?->preview_status === 'ready' ? $existing->preview_mime_type : null,
                'preview_size' => $existing?->preview_status === 'ready' ? $existing->preview_size : null,
                'preview_error' => null,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ]),
        );

        if ($previewStatus === 'pending' && $existing?->preview_status !== 'ready') {
            GenerateVideoPreview::dispatch($mediaId, $path);
        }
    }

    private function downloadVideo(array $video, string $path): bool
    {
        $fileName = trim((string) ($video['file_name'] ?? ''));
        if ($fileName === '') {
            return false;
        }

        $url = 'https://commons.wikimedia.org/wiki/Special:Redirect/file/'.rawurlencode($fileName);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'JOD-Syria-Content-Seeder/1.0 (+https://github.com/MustafaFares445/jod)',
                'Accept' => 'video/webm,application/octet-stream;q=0.9,*/*;q=0.1',
            ])->timeout(90)->retry(2, 750)->get($url);

            if (! $response->successful()) {
                return false;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/json')) {
                return false;
            }

            $bytes = $response->body();
            $size = strlen($bytes);
            if ($size <= 1024 || $size > self::MAX_VIDEO_BYTES) {
                return false;
            }

            $expected = (int) ($video['expected_size'] ?? 0);
            if ($expected > 0 && abs($size - $expected) > max(2 * 1024 * 1024, (int) round($expected * 0.15))) {
                $this->command?->warn('Downloaded video size differs from the documented source snapshot: '.$video['key']);
            }

            Storage::disk('public')->put($path, $bytes);
            return true;
        } catch (Throwable $exception) {
            $this->command?->warn('Could not download real video '.$video['key'].': '.$exception->getMessage());
            return false;
        }
    }

    /** @return array<string, string> */
    private function loadCategoryIds(): array
    {
        if (! Schema::hasTable('categories')) {
            return [];
        }

        $map = [];
        foreach (DB::table('categories')->get(['id', 'name']) as $category) {
            $key = match ((string) $category->name) {
                'الصحة' => 'health',
                'التعليم' => 'education',
                'الغذاء' => 'food',
                'الطوارئ' => 'emergency',
                'الإيواء' => 'shelter',
                'التوظيف' => 'employment',
                'التطوع' => 'volunteering',
                'الحماية' => 'protection',
                'المياه والإصحاح' => 'wash',
                'الأطفال' => 'children',
                'ذوي الإعاقة' => 'disability',
                'إعادة الإعمار' => 'reconstruction',
                default => null,
            };

            if ($key !== null) {
                $map[$key] = (string) $category->id;
            }
        }

        return $map;
    }

    private function resolveAdminAuthorId(): ?string
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        $id = DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id')
            ?? DB::table('users')->whereNull('organization_id')->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');

        return $id === null ? null : (string) $id;
    }

    private function columns(string $table, array $attributes): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        return array_intersect_key($attributes, $columns);
    }

    private function id(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-syria-video:'.$key), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
