<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);

        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $sort = (string) ($this->queryParam($request, 'sort') ?? '-submittedAt');
        $sortBy = (string) ($this->queryParam($request, 'sortBy') ?? '');

        $query = Campaign::query()
            ->with(['organization', 'creator', 'reviewedBy'])
            ->when(($status = $this->queryParam($request, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($organizationId = $this->queryParam($request, 'filter.organizationId')) && $organizationId !== 'all', fn (Builder $builder) => $builder->where('organization_id', $organizationId))
            ->when(($organizationName = $this->queryParam($request, 'filter.organizationName')) && $organizationName !== 'all', function (Builder $builder) use ($organizationName): void {
                $builder->whereHas('organization', fn (Builder $org) => $org->where('name', 'like', '%'.$organizationName.'%'));
            })
            ->when(($category = $this->queryParam($request, 'filter.category')) && $category !== 'all', fn (Builder $builder) => $builder->where('category', $category));

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

        return CampaignResource::collection($query->paginate($perPage));
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorize('view', $campaign);

        return CampaignResource::make($campaign->loadMissing(['organization', 'creator', 'reviewedBy']));
    }

    public function approve(Request $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('approve', $campaign);

        $this->assertPending($campaign);

        $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'rejection_reason' => null,
        ]);

        return CampaignResource::make($campaign->refresh()->loadMissing(['organization', 'creator', 'reviewedBy']));
    }

    public function reject(Request $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('reject', $campaign);

        $this->assertPending($campaign);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8'],
        ]);

        $campaign->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'rejection_reason' => $data['reason'],
        ]);

        return CampaignResource::make($campaign->refresh()->loadMissing(['organization', 'creator', 'reviewedBy']));
    }

    private function assertPending(Campaign $campaign): void
    {
        if ($campaign->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending campaigns can be reviewed.'],
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
