<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class BlogService
{
    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null, search?: string|null, category?: string|null, sort?: string|null}  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        $query = $this->publicQuery()
            ->when(
                filled($params['category'] ?? null),
                fn (Builder $builder) => $builder->where('category', $params['category']),
            )
            ->when(filled($params['search'] ?? null), function (Builder $builder) use ($params): void {
                $search = (string) $params['search'];
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%");
                });
            });

        if (($params['sort'] ?? 'newest') === 'oldest') {
            $query->orderBy('published_at');
        } else {
            $query->orderByDesc('published_at');
        }

        return $query->orderBy('id')->paginate($perPage);
    }

    public function findPublic(string $id): ?Article
    {
        return $this->publicQuery()
            ->whereKey($id)
            ->first();
    }

    /**
     * @return Builder<Article>
     */
    private function publicQuery(): Builder
    {
        return Article::query()
            ->with('author')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
