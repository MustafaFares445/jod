<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CategoryData;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService
{
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
