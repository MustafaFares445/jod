<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Data\BadgeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Badges\BadgeFilterRequest;
use App\Http\Requests\Badges\BadgeRequest;
use App\Http\Resources\BadgeResource;
use App\Models\Badge;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BadgeController extends Controller
{
    public function __construct(protected BadgeService $service) {}

    public function index(BadgeFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Badge::class);

        $queryParams = $request->query();
        $activeFilter = $this->queryParam($queryParams, 'filter.isActive');
        $searchFilter = $this->queryParam($queryParams, 'filter.search') ?? $request->get('search');

        $badges = Badge::query()
            ->when($activeFilter !== null, fn ($q) => $q->where('is_active', (bool) $activeFilter))
            ->when($searchFilter, fn ($q) => $q->where('name', 'LIKE', '%' . $searchFilter . '%'))
            ->orderByDesc('created_at')
            ->paginate($request->get('perPage', 20));

        return BadgeResource::collection($badges);
    }

    public function store(BadgeRequest $request): BadgeResource
    {
        $this->authorize('create', Badge::class);

        $badge = $this->service->store(BadgeData::from($request->validated()));

        return BadgeResource::make($badge);
    }

    public function show(Badge $badge): BadgeResource
    {
        $this->authorize('view', $badge);

        return BadgeResource::make($badge);
    }

    public function update(BadgeRequest $request, Badge $badge): BadgeResource
    {
        $this->authorize('update', $badge);

        $updated = $this->service->update(BadgeData::from($request->validated()), $badge);

        return BadgeResource::make($updated);
    }

    public function updateStatus(Request $request, Badge $badge): BadgeResource
    {
        $this->authorize('update', $badge);

        $request->validate(['isActive' => ['required', 'boolean']]);

        $updated = $this->service->updateStatus($badge, $request->get('isActive'));

        return BadgeResource::make($updated);
    }

    public function destroy(Badge $badge): Response
    {
        $this->authorize('delete', $badge);

        $badge->delete();

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
