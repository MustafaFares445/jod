<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Data\ArticleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Articles\ArticleFilterRequest;
use App\Http\Requests\Articles\ArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\ArticleService;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ArticleController extends Controller
{
    public function __construct(protected ArticleService $service) {}

    public function index(ArticleFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Article::class);

        $queryParams = $request->query();
        $statusFilter = $this->queryParam($queryParams, 'filter.status');
        $search = SearchFilter::fromArray($queryParams);

        $articles = Article::query()
            ->when($statusFilter, fn (Builder $query) => $query->where('status', $statusFilter))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->get('perPage', 20));

        return ArticleResource::collection($articles);
    }

    public function store(ArticleRequest $request): ArticleResource
    {
        $this->authorize('create', Article::class);

        $article = $this->service->store(ArticleData::from($request->validated()));

        return ArticleResource::make($article);
    }

    public function show(Article $article): ArticleResource
    {
        $this->authorize('view', $article);

        return ArticleResource::make($article);
    }

    public function update(ArticleRequest $request, Article $article): ArticleResource
    {
        $this->authorize('update', $article);

        $updated = $this->service->update(ArticleData::from($request->validated()), $article);

        return ArticleResource::make($updated);
    }

    public function destroy(Article $article): Response
    {
        $this->authorize('delete', $article);

        $article->delete();

        return response()->noContent();
    }

    private function queryParam(array $queryParams, string $key): mixed
    {
        if (array_key_exists($key, $queryParams)) {
            return $queryParams[$key];
        }

        $flatKey = str_replace('.', '_', $key);
        if (array_key_exists($flatKey, $queryParams)) {
            return $queryParams[$flatKey];
        }

        return data_get($queryParams, $key);
    }
}
