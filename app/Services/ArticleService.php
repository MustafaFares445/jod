<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ArticleData;
use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArticleService
{
    public function store(ArticleData $data): Article
    {
        return DB::transaction(static function () use ($data) {
            $attributes = $data->onlyModelAttributes();

            if (empty($attributes['slug']) && ! empty($attributes['title'])) {
                $attributes['slug'] = Str::slug($attributes['title']);
            }

            if (($attributes['status'] ?? null) === 'published' && empty($attributes['published_at'])) {
                $attributes['published_at'] = now();
            }

            return Article::create($attributes);
        });
    }

    public function update(ArticleData $data, Article $article): Article
    {
        return DB::transaction(static function () use ($data, $article) {
            $attributes = $data->onlyModelAttributes();

            if (empty($attributes['slug']) && ! empty($attributes['title'])) {
                $attributes['slug'] = Str::slug($attributes['title']);
            }

            if (($attributes['status'] ?? null) === 'published' && empty($attributes['published_at'])) {
                $attributes['published_at'] = now();
            }

            tap($article)->update($attributes);
            return $article;
        });
    }
}
