<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\NotificationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\NotificationEventService;
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

    public function __construct(private readonly NotificationEventService $notifications) {}

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
        $now = now();

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
            'published_at' => in_array($status, ['published', 'approved'], true) ? $now : null,
            'reviewed_at' => $status === 'approved' ? $now : null,
            'reviewed_by' => $status === 'approved' ? $actorId : null,
            'approved_at' => $status === 'approved' ? $now : null,
            'approved_by' => $status === 'approved' ? $actorId : null,
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
        $actorId = (string) $request->user()->id;
        $updates = ['updated_by' => $actorId];

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
            $status = (string) $data['status'];
            $now = now();
            $updates['status'] = $status;
            $updates['published_at'] = in_array($status, ['published', 'approved'], true)
                ? ($post->published_at ?? $now)
                : null;

            if ($status === 'approved') {
                $updates['reviewed_at'] = $now;
                $updates['reviewed_by'] = $actorId;
                $updates['approved_at'] = $now;
                $updates['approved_by'] = $actorId;
                $updates['rejected_at'] = null;
                $updates['rejected_by'] = null;
                $updates['rejection_reason'] = null;
            } elseif ($status === 'rejected') {
                $updates['reviewed_at'] = $now;
                $updates['reviewed_by'] = $actorId;
                $updates['rejected_at'] = $now;
                $updates['rejected_by'] = $actorId;
                $updates['approved_at'] = null;
                $updates['approved_by'] = null;
            }
        }

        $previousStatus = (string) $post->status;
        $post->update($updates);
        $post->refresh();

        if (array_key_exists('status', $data) && $previousStatus !== $post->status) {
            $this->notifyStatusChange($post, $actorId);
        }

        return PostResource::make($post->load(self::RELATIONS));
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

    private function notifyStatusChange(Post $post, string $actorId): void
    {
        if (! filled($post->author_id) || ! in_array($post->status, ['approved', 'rejected'], true)) {
            return;
        }

        $title = filled($post->title) ? (string) $post->title : 'منشورك';

        if ($post->status === 'approved') {
            $this->notifications->notifyUser(
                (string) $post->author_id,
                NotificationEventType::PostApproved,
                'تمت الموافقة على منشورك',
                "تمت الموافقة على «{$title}» ونشره على المنصة.",
                'post',
                'high',
                $title,
                '/posts/'.$post->id,
                null,
                $actorId,
            );

            return;
        }

        $this->notifications->notifyUser(
            (string) $post->author_id,
            NotificationEventType::PostRejected,
            'تم رفض منشورك',
            "تم رفض «{$title}» من إدارة المنصة.",
            'post',
            'high',
            $title,
            '/my-posts/'.$post->id,
            null,
            $actorId,
        );
    }
}
