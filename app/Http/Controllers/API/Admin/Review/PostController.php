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
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $sort = (string) ($this->queryParam($request, 'sort') ?? '-submittedAt');
        $sortBy = (string) ($this->queryParam($request, 'sortBy') ?? '');

        $query = Post::query()
            ->with(['organization', 'campaign', 'reviewedBy'])
            ->when(($status = $this->queryParam($request, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($organizationId = $this->queryParam($request, 'filter.organizationId')) && $organizationId !== 'all', fn (Builder $builder) => $builder->where('organization_id', $organizationId))
            ->when(($organizationName = $this->queryParam($request, 'filter.organizationName')) && $organizationName !== 'all', function (Builder $builder) use ($organizationName): void {
                $builder->whereHas('organization', fn (Builder $org) => $org->where('name', 'like', '%'.$organizationName.'%'));
            })
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
            'submittedAt' => $query->orderBy('created_at'),
            '-submittedAt' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return PostResource::collection($query->paginate($perPage));
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('view', $post);

        return PostResource::make($post->loadMissing(['organization', 'campaign', 'reviewedBy']));
    }

    public function approve(Request $request, Post $post): PostResource
    {
        $this->authorize('approve', $post);

        $this->assertPending($post);

        $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $post->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'rejection_reason' => null,
        ]);

        return PostResource::make($post->refresh()->loadMissing(['organization', 'campaign', 'reviewedBy']));
    }

    public function reject(Request $request, Post $post): PostResource
    {
        $this->authorize('reject', $post);

        $this->assertPending($post);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8'],
        ]);

        $post->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'rejection_reason' => $data['reason'],
        ]);

        return PostResource::make($post->refresh()->loadMissing(['organization', 'campaign', 'reviewedBy']));
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
