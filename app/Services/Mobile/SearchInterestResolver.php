<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class SearchInterestResolver
{
    public function resolve(string $query): ?Category
    {
        $query = $this->normalize($query);
        if ($query === '') return null;

        $categories = Category::query()
            ->where('status', 'active')
            ->get(['id', 'name']);

        foreach ($categories as $category) {
            if ($this->normalize((string) $category->name) === $query) return $category;
        }

        $scores = [];
        $exactMatches = [];
        $keywords = DB::table('category_keywords')
            ->join('categories', 'categories.id', '=', 'category_keywords.category_id')
            ->where('categories.status', 'active')
            ->get(['category_keywords.category_id', 'category_keywords.keyword']);

        foreach ($keywords as $row) {
            $keyword = $this->normalize((string) $row->keyword);
            if ($keyword === '') continue;

            if ($query === $keyword) {
                $exactMatches[(string) $row->category_id] = true;
                continue;
            }

            if (str_contains($query, $keyword) || str_contains($keyword, $query)) {
                $scores[(string) $row->category_id] = ($scores[(string) $row->category_id] ?? 0) + mb_strlen($keyword);
            }
        }

        if (count($exactMatches) === 1) {
            return Category::query()->find(array_key_first($exactMatches));
        }
        if (count($exactMatches) > 1 || $scores === []) return null;

        arsort($scores);
        $ids = array_keys($scores);
        $top = $scores[$ids[0]];
        $second = isset($ids[1]) ? $scores[$ids[1]] : null;
        if ($second !== null && $top === $second) return null;

        return Category::query()->find($ids[0]);
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = str_replace('ـ', '', $value);
        $value = strtr($value, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
            'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
