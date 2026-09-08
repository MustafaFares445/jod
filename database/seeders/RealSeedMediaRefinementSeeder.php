<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class RealSeedMediaRefinementSeeder extends Seeder
{
    /** @var array<string, array{path:string,mime:string,size:int}|null> */
    private array $downloaded = [];

    /** @var array<string, array{file_name:string,source_page:string}> */
    private array $fallbacks = [];

    public function run(): void
    {
        if (app()->environment('testing') || ! Schema::hasTable('media')) {
            return;
        }

        $manifest = json_decode(
            (string) file_get_contents(database_path('data/syrian_real_image_fallbacks.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->fallbacks = $manifest['fallbacks'] ?? [];

        $placeholders = DB::table('media')
            ->where(function ($query): void {
                $query
                    ->where('mime_type', 'image/svg+xml')
                    ->orWhere('path', 'like', '%/fallback/%')
                    ->orWhere('path', 'like', '%student-assistance.png')
                    ->orWhere(function ($groupAvatar): void {
                        $groupAvatar
                            ->where('model_type', 'group')
                            ->where('prop', 'avatar')
                            ->where('path', 'like', '%/groups/%-avatar.png');
                    });
            })
            ->get(['id', 'model_type', 'model_id', 'path']);

        foreach ($placeholders as $media) {
            $category = $this->categoryFor((string) $media->model_type, (string) $media->model_id);
            $real = $this->realImageFor($category);

            if ($real === null) {
                // A missing image is preferable to exposing an artificial placeholder as real content.
                DB::table('media')->where('id', $media->id)->delete();
                continue;
            }

            DB::table('media')->where('id', $media->id)->update([
                'disk' => 'public',
                'path' => $real['path'],
                'original_name' => basename($real['path']),
                'description' => $this->descriptionFor($category),
                'mime_type' => $real['mime'],
                'size' => $real['size'],
                'updated_at' => now(),
            ]);
        }
    }

    private function categoryFor(string $modelType, string $modelId): string
    {
        if ($modelType === 'post' && Schema::hasTable('posts')) {
            $categoryId = DB::table('posts')->where('id', $modelId)->value('category_id');
            return $this->categoryKey($categoryId ? (string) $categoryId : null);
        }

        if ($modelType === 'campaign' && Schema::hasTable('campaigns')) {
            $categoryId = DB::table('campaigns')->where('id', $modelId)->value('category_id');
            return $this->categoryKey($categoryId ? (string) $categoryId : null);
        }

        if ($modelType === 'group') {
            return 'volunteering';
        }

        return 'organization';
    }

    private function categoryKey(?string $categoryId): string
    {
        if ($categoryId === null || ! Schema::hasTable('categories')) {
            return 'organization';
        }

        $name = (string) DB::table('categories')->where('id', $categoryId)->value('name');

        return match ($name) {
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
            default => 'organization',
        };
    }

    /** @return array{path:string,mime:string,size:int}|null */
    private function realImageFor(string $category): ?array
    {
        if (array_key_exists($category, $this->downloaded)) {
            return $this->downloaded[$category];
        }

        $source = $this->fallbacks[$category] ?? $this->fallbacks['organization'] ?? null;
        if (! is_array($source) || empty($source['file_name'])) {
            return $this->downloaded[$category] = null;
        }

        $url = 'https://commons.wikimedia.org/wiki/Special:Redirect/file/'.rawurlencode((string) $source['file_name']);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'JOD-Syria-Content-Seeder/1.0 (+https://github.com/MustafaFares445/jod)',
                'Accept' => 'image/avif,image/webp,image/png,image/jpeg,*/*;q=0.7',
            ])->timeout(30)->retry(2, 500)->get($url);

            $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if (! $response->successful() || ! str_starts_with($mime, 'image/')) {
                return $this->downloaded[$category] = null;
            }

            $bytes = $response->body();
            if (strlen($bytes) < 2048) {
                return $this->downloaded[$category] = null;
            }

            $extension = match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'avif') => 'avif',
                default => 'jpg',
            };
            $path = 'content/syria/real-images/'.$category.'.'.$extension;
            Storage::disk('public')->put($path, $bytes);

            return $this->downloaded[$category] = [
                'path' => $path,
                'mime' => $mime,
                'size' => strlen($bytes),
            ];
        } catch (Throwable $exception) {
            $this->command?->warn('Could not download real image for '.$category.': '.$exception->getMessage());
            return $this->downloaded[$category] = null;
        }
    }

    private function descriptionFor(string $category): string
    {
        return match ($category) {
            'health' => 'صورة مرتبطة بالرعاية الصحية والخدمات الطبية.',
            'education' => 'صورة مرتبطة بالتعليم ودعم الطلاب.',
            'food' => 'صورة مرتبطة بالمساعدات الغذائية والأمن الغذائي.',
            'emergency' => 'صورة مرتبطة بالاستجابة الإنسانية الطارئة.',
            'shelter' => 'صورة مرتبطة بالإيواء ودعم الأسر المتضررة.',
            'employment' => 'صورة مرتبطة بالتدريب والمهارات وسبل العيش.',
            'volunteering' => 'صورة مرتبطة بالعمل التطوعي والمبادرات المجتمعية.',
            'protection' => 'صورة مرتبطة بالحماية والدعم المجتمعي.',
            'wash' => 'صورة مرتبطة بالمياه والإصحاح والنظافة.',
            'children' => 'صورة مرتبطة بدعم الأطفال والتعليم.',
            'disability' => 'صورة مرتبطة بخدمات الدعم والحماية.',
            'reconstruction' => 'صورة مرتبطة بالتعافي وإعادة تأهيل البنية التحتية.',
            default => 'صورة مرتبطة بالعمل الإنساني والمجتمعي.',
        };
    }
}
