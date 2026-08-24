<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CategoryData;
use App\Models\Category;
use App\Support\SearchFilter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService
{
    public function discover(array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = (string) ($params['sort'] ?? '-createdAt');
        $search = SearchFilter::fromArray($params);

        $query = Category::query()
            ->where('status', 'active')
            ->when(filled($params['target'] ?? null), fn ($builder) => $builder->where('target', $params['target']))
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('target', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'createdAt' => $query->orderBy('created_at'),
            '-createdAt' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate($perPage);
    }

    public function store(CategoryData $data): Category
    {
        return DB::transaction(static function () use ($data) {
            $attributes = $data->onlyModelAttributes();
            $attributes['id'] = (string) Str::uuid();
            $attributes['status'] = $attributes['status'] ?? 'active';

            return Category::create($attributes);
        });
    }

    public function update(CategoryData $data, Category $category): Category
    {
        return DB::transaction(static function () use ($data, $category) {
            tap($category)->update($data->onlyModelAttributes());

            return $category;
        });
    }

    public function updateStatus(Category $category, string $status): Category
    {
        return DB::transaction(static function () use ($category, $status) {
            tap($category)->update(['status' => $status]);

            return $category;
        });
    }
}
