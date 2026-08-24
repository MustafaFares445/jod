<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    private const RELATIONS = [
        'organization',
        'campaign',
        'category',
        'images',
        'author',
        'updatedBy',
        'reviewedBy',
        'approvedBy',
        'rejectedBy',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $search = SearchFilter::fromArray($request->query());
        $status = $this->queryParam($request, 'filter.status') ?? $request->query('status');
        $type = $this->queryParam($request, 'filter.type') ?? $request->query('type');
        $location = $this->queryParam($request, 'filter.location') ?? $request->query('location');
        $categoryId = $this->queryParam($request, 'filter.categoryId') ?? $request->query('categoryId');
        $organizationId = $this->queryParam($request, 'filter.organizationId') ?? $request->query('organizationId');
        $authorId = $this->queryParam($request, 'filter.authorId') ?? $request->query('authorId');
        $sort = (string) ($request->query('sort') ?: '-createdAt');

        $query = Post::query()
            ->with(self::RELATIONS)
            ->when($status && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when($type && $type !== 'all', fn (Builder $builder) => $builder->where('type', $type))
            ->when(filled($location), fn (Builder $builder) => $builder->where('location', 'like', '%'.$location.'%'))
            ->when(filled($categoryId), fn (Builder $builder) => $builder->where('category_id', $categoryId))
            ->when(filled($organizationId), fn (Builder $builder) => $builder->where('organization_id', $organizationId))
            ->when(filled($authorId), fn (Builder $builder) => $builder->where('author_id', $authorId))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('organization', function (Builder $organization) use ($search): void {
                            $organization->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('location', 'like', "%{$search}%");
                        })
                        ->orWhereHas('author', function (Builder $author) use ($search): void {
                            $author->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        match ($sort) {
            'title' => $query->orderBy('title'),
            '-title' => $query->orderByDesc('title'),
            'createdAt' => $query->orderBy('created_at'),
            '-createdAt' => $query->orderByDesc('created_at'),
            'updatedAt' => $query->orderBy('updated_at'),
            '-updatedAt' => $query->orderByDesc('updated_at'),
            'publishedAt' => $query->orderBy('published_at'),
            '-publishedAt' => $query->orderByDesc('published_at'),
            default => $query->orderByDesc('created_at'),
        };

        return PostResource::collection($query->orderBy('id')->paginate($perPage));
    }

    public function store(AdminPostRequest $request): PostResource
    {
        $this->authorize('createAdmin', Post::class);

        $data = $request->validated();
        $content = (string) ($data['content'] ?? $data['description'] ?? '');
        $status = (string) ($data['status'] ?? 'published');
        $actorId = (string) $request->user()->id;

        $post = Post::query()->create([
            'title' => $data['title'],
            'summary' => $data['summary'] ?? mb_substr($content, 0, 255),
            'content' => $content,
            'type' => $data['type'] ?? 'general',
            'status' => $status,
            'location' => $data['location'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'organization_id' => $data['organization_id'] ?? null,
            'author_id' => $data['author_id'] ?? $actorId,
            'updated_by' => $actorId,
            'published_at' => in_array($status, ['published', 'approved'], true) ? now() : null,
        ]);

        return PostResource::make($post->load(self::RELATIONS));
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('viewAdmin', $post);

        return PostResource::make($post->loadMissing(self::RELATIONS));
    }

    public function update(AdminPostRequest $request, Post $post): PostResource
    {
        $this->authorize('updateAdmin', $post);

        $data = $request->validated();
        $updates = ['updated_by' => (string) $request->user()->id];

        foreach (['title', 'summary', 'type', 'location', 'category_id', 'campaign_id', 'organization_id', 'author_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('content', $data) || array_key_exists('description', $data)) {
            $content = (string) ($data['content'] ?? $data['description'] ?? '');
            $updates['content'] = $content;

            if (! array_key_exists('summary', $data)) {
                $updates['summary'] = mb_substr($content, 0, 255);
            }
        }

        if (array_key_exists('status', $data)) {
            $updates['status'] = $data['status'];
            $updates['published_at'] = in_array($data['status'], ['published', 'approved'], true)
                ? ($post->published_at ?? now())
                : null;
        }

        $post->update($updates);

        return PostResource::make($post->refresh()->load(self::RELATIONS));
    }

    public function destroy(Post $post): Response
    {
        $post->loadMissing('images');
        $this->authorize('deleteAdmin', $post);

        foreach ($post->images as $media) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }

        $post->delete();

        return response()->noContent();
    }

    private function queryParam(Request $request, string $key): mixed
    {
        $query = $request->query();

        if (array_key_exists($key, $query)) {
            return $query[$key];
        }

        $flatKey = str_replace('.', '_', $key);

        return $query[$flatKey] ?? data_get($query, $key);
    }
}
