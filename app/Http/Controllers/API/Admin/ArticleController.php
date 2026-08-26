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
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    private const RELATIONS = ['author', 'media'];

    public function __construct(protected ArticleService $service) {}

    public function index(ArticleFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Article::class);

        $queryParams = $request->query();
        $statusFilter = $this->queryParam($queryParams, 'filter.status');
        $search = SearchFilter::fromArray($queryParams);

        $articles = Article::query()
            ->with(self::RELATIONS)
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

        $data = $request->validated();
        $description = trim((string) $data['description']);
        $actor = $request->user();
        $article = $this->service->store(ArticleData::from([
            'title' => trim((string) $data['title']),
            'excerpt' => mb_substr($description, 0, 500),
            'content' => $description,
            'status' => 'published',
            'authorName' => (string) $actor->name,
            'authorId' => (string) $actor->id,
        ]));

        return ArticleResource::make($article->load(self::RELATIONS));
    }

    public function show(Article $article): ArticleResource
    {
        $this->authorize('view', $article);

        return ArticleResource::make($article->loadMissing(self::RELATIONS));
    }

    public function update(ArticleRequest $request, Article $article): ArticleResource
    {
        $this->authorize('update', $article);

        $data = $request->validated();
        $description = array_key_exists('description', $data)
            ? trim((string) $data['description'])
            : (string) ($article->content ?? $article->excerpt ?? '');

        $updated = $this->service->update(ArticleData::from([
            'title' => isset($data['title']) ? trim((string) $data['title']) : (string) $article->title,
            'excerpt' => mb_substr($description, 0, 500),
            'content' => $description,
            'status' => 'published',
            'authorName' => (string) $article->author_name,
            'authorId' => $article->author_id !== null ? (string) $article->author_id : null,
        ]), $article);

        return ArticleResource::make($updated->load(self::RELATIONS));
    }

    public function destroy(Article $article): Response
    {
        $this->authorize('delete', $article);
        $article->loadMissing('media');

        foreach ($article->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }

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
