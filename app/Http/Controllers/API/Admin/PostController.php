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
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    private const RELATIONS = [
        'organization', 'campaign', 'category', 'images', 'videos', 'author',
        'updatedBy', 'reviewedBy', 'blockedBy',
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
        $authorId = $this->queryParam($request, 'filter.authorId') ?? $request->query('authorId');
        $sort = (string) ($request->query('sort') ?: '-createdAt');

        $query = Post::query()
            ->with(self::RELATIONS)
            ->whereNull('organization_id')
            ->whereHas('author', fn (Builder $author) => $author->where('user_type', '!=', 'admin'))
            ->when($status && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when($type && $type !== 'all', fn (Builder $builder) => $builder->where('type', $type))
            ->when(filled($location), fn (Builder $builder) => $builder->where('location', 'like', '%'.$location.'%'))
            ->when(filled($categoryId), fn (Builder $builder) => $builder->where('category_id', $categoryId))
            ->when(filled($authorId), fn (Builder $builder) => $builder->where('author_id', $authorId))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('author', function (Builder $author) use ($search): void {
                            $author->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        match ($sort) {
            'title' => $query->orderBy('title'), '-title' => $query->orderByDesc('title'),
            'createdAt' => $query->orderBy('created_at'), '-createdAt' => $query->orderByDesc('created_at'),
            'updatedAt' => $query->orderBy('updated_at'), '-updatedAt' => $query->orderByDesc('updated_at'),
            'publishedAt' => $query->orderBy('published_at'), '-publishedAt' => $query->orderByDesc('published_at'),
            default => $query->orderByDesc('created_at'),
        };

        return PostResource::collection($query->orderBy('id')->paginate($perPage));
    }

    public function store(AdminPostRequest $request): PostResource
    {
        $this->authorize('createAdmin', Post::class);
        $data = $request->validated();
        $content = trim((string) $data['description']);
        $actorId = (string) $request->user()->id;
        $status = (string) ($data['status'] ?? 'published');
        if (! in_array($status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['status' => ['Admin-authored posts support only draft or published status.']]);
        }

        $post = Post::query()->create([
            'title' => trim((string) $data['title']),
            'summary' => mb_substr($content, 0, 255),
            'content' => $content,
            'type' => 'general',
            'status' => $status,
            'organization_id' => null,
            'campaign_id' => null,
            'author_id' => $actorId,
            'updated_by' => $actorId,
            'published_at' => $status === 'published' ? now() : null,
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
        $data = $request->validated();
        $status = array_key_exists('status', $data) ? (string) $data['status'] : null;

        if ($post->status === 'pending' && in_array($status, ['published', 'blocked'], true)) {
            return $this->reviewUserPost($request, $post, $data, $status);
        }

        $this->authorize('updateAdmin', $post);
        if ($status !== null && ! in_array($status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['status' => ['Admin-authored posts support only draft or published status.']]);
        }

        $actorId = (string) $request->user()->id;
        $updates = ['updated_by' => $actorId];
        if (array_key_exists('title', $data)) $updates['title'] = trim((string) $data['title']);
        if (array_key_exists('description', $data)) {
            $content = trim((string) $data['description']);
            $updates['content'] = $content;
            $updates['summary'] = mb_substr($content, 0, 255);
        }
        if ($status !== null) {
            $updates['status'] = $status;
            $updates['published_at'] = $status === 'published' ? ($post->published_at ?? now()) : null;
        }

        $post->update($updates);
        return PostResource::make($post->refresh()->load(self::RELATIONS));
    }

    public function destroy(Post $post): Response
    {
        $post->loadMissing('media');
        $this->authorize('deleteAdmin', $post);
        foreach ($post->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }
        $post->delete();
        return response()->noContent();
    }

    private function reviewUserPost(Request $request, Post $post, array $data, string $status): PostResource
    {
        $this->authorize($status === 'published' ? 'publishUserPost' : 'blockUserPost', $post);
        if ($post->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Only pending user posts can be reviewed.']]);
        }
        if (array_key_exists('title', $data) || array_key_exists('description', $data)) {
            throw ValidationException::withMessages(['post' => ['Admin review cannot edit user post content.']]);
        }

        $actorId = (string) $request->user()->id;
        $now = now();
        $updates = [
            'status' => $status,
            'updated_by' => $actorId,
            'reviewed_at' => $now,
            'reviewed_by' => $actorId,
        ];

        if ($status === 'published') {
            $updates += [
                'published_at' => $post->published_at ?? $now,
                'blocked_at' => null,
                'blocked_by' => null,
                'block_reason' => null,
            ];
        } else {
            $updates += [
                'published_at' => null,
                'blocked_at' => $now,
                'blocked_by' => $actorId,
                'block_reason' => trim((string) ($data['blockReason'] ?? '')),
            ];
        }

        $post->update($updates);
        $post->refresh();
        $this->notifyStatusChange($post, $actorId);
        return PostResource::make($post->load(self::RELATIONS));
    }

    private function queryParam(Request $request, string $key): mixed
    {
        $query = $request->query();
        if (array_key_exists($key, $query)) return $query[$key];
        $flatKey = str_replace('.', '_', $key);
        return $query[$flatKey] ?? data_get($query, $key);
    }

    private function notifyStatusChange(Post $post, string $actorId): void
    {
        if (! filled($post->author_id) || ! in_array($post->status, ['published', 'blocked'], true)) return;
        $title = filled($post->title) ? (string) $post->title : 'منشورك';

        if ($post->status === 'published') {
            $this->notifications->notifyUser(
                (string) $post->author_id,
                NotificationEventType::PostPublished,
                'تم نشر منشورك',
                "تمت مراجعة «{$title}» ونشره على المنصة.",
                'post', 'high', $title, '/posts/'.$post->id, null, $actorId,
            );
            $this->notifications->notifyPublisherFollowers(
                'user',
                (string) $post->author_id,
                NotificationEventType::PostPublished,
                'منشور جديد من حساب تتابعه',
                "نشر {$post->author?->name} «{$title}».",
                'post', 'normal', $title, '/posts/'.$post->id, null, $actorId,
            );
            return;
        }

        $this->notifications->notifyUser(
            (string) $post->author_id,
            NotificationEventType::PostBlocked,
            'تم رفض منشورك',
            "تم رفض «{$title}». السبب: {$post->block_reason}",
            'post', 'high', $title, '/my-posts/'.$post->id, null, $actorId,
        );
    }
}
