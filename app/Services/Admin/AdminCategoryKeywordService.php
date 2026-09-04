<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\CategoryKeyword;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminCategoryKeywordService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function keywords(Category $category): array
    {
        return CategoryKeyword::query()
            ->where('category_id', $category->id)
            ->orderBy('keyword')
            ->pluck('keyword')
            ->values()
            ->all();
    }

    public function replace(User $actor, Category $category, array $keywords): array
    {
        $normalized = collect($keywords)
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->unique(fn (string $keyword): string => mb_strtolower($keyword))
            ->values();
        $old = $this->keywords($category);

        DB::transaction(function () use ($category, $normalized): void {
            CategoryKeyword::query()->where('category_id', $category->id)->delete();
            foreach ($normalized as $keyword) {
                CategoryKeyword::query()->create([
                    'category_id' => $category->id,
                    'keyword' => $keyword,
                ]);
            }
        });

        $new = $this->keywords($category);
        $this->audit->record(
            $actor,
            'category.keywords_updated',
            'category',
            (string) $category->id,
            ['keywords' => $old],
            ['keywords' => $new],
        );

        return $new;
    }
}
