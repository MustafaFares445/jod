<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    private const RELATIONS = [
        'campaign',
        'organization',
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
        $sort = (string) ($this->queryParam($request, 'sort') ?? '-submittedAt');
        $sortBy = (string) ($this->queryParam($request, 'sortBy') ?? '');

        $query = Post::query()
            ->with(self::RELATIONS)
            ->whereNull('organization_id')
            ->when(($status = $this->queryParam($request, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($type = $this->queryParam($request, 'filter.type')) && $type !== 'all', fn (Builder $builder) => $builder->where('type', $type));

        $normalizedSort = $sort !== '' ? $sort : match ($sortBy) {
            'title_asc' => 'title',
            'title_desc' => '-title',
            'created_at_oldest' => 'submittedAt',
            'created_at_newest' => '-submittedAt',
            default => '-submittedAt',
        };

        match ($normalizedSort) {
            'title' => $query->orderBy('title'),
            '-title' => $query->orderByDesc('title'),
            'submittedAt' => $query->orderByRaw('COALESCE(submitted_at, created_at) ASC'),
            '-submittedAt' => $query->orderByRaw('COALESCE(submitted_at, created_at) DESC'),
            default => $query->orderByRaw('COALESCE(submitted_at, created_at) DESC'),
        };

        return PostResource::collection($query->paginate($perPage));
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('view', $post);

        return PostResource::make($post->loadMissing(self::RELATIONS));
    }

    public function approve(Request $request, Post $post): PostResource
    {
        $this->authorize('approve', $post);
        $this->assertPending($post);

        $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $now = now();
        $post->update([
            'status' => 'approved',
            'published_at' => $now,
            'reviewed_at' => $now,
            'reviewed_by' => auth()->id(),
            'approved_at' => $now,
            'approved_by' => auth()->id(),
            'rejection_reason' => null,
        ]);

        return PostResource::make($post->refresh()->loadMissing(self::RELATIONS));
    }

    public function reject(Request $request, Post $post): PostResource
    {
        $this->authorize('reject', $post);
        $this->assertPending($post);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8'],
        ]);

        $now = now();
        $post->update([
            'status' => 'rejected',
            'reviewed_at' => $now,
            'reviewed_by' => auth()->id(),
            'rejected_at' => $now,
            'rejected_by' => auth()->id(),
            'rejection_reason' => $data['reason'],
        ]);

        return PostResource::make($post->refresh()->loadMissing(self::RELATIONS));
    }

    private function assertPending(Post $post): void
    {
        if ($post->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending posts can be reviewed.'],
            ]);
        }
    }

    private function queryParam(Request $request, string $key): mixed
    {
        $queryParams = $request->query();

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
