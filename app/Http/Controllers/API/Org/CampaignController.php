<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Data\CampaignData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\CampaignRequest;
use App\Http\Requests\Org\CloseCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAnyOrganization', Campaign::class);

        $campaigns = $this->service->paginate(request()->all(), $this->organizationId());

        return CampaignResource::collection($campaigns);
    }

    public function store(CampaignRequest $request): CampaignResource
    {
        $this->authorize('createOrganization', Campaign::class);

        $campaign = $this->service->create(
            CampaignData::from($request->validated()),
            $this->organizationId(),
        );

        return CampaignResource::make($campaign);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorize('viewOrganization', $campaign);

        return CampaignResource::make($campaign);
    }

    public function update(CampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('updateOrganization', $campaign);

        $campaign = $this->service->update(
            $campaign,
            CampaignData::from($request->validated()),
        );

        return CampaignResource::make($campaign);
    }

    public function close(CloseCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('closeOrganization', $campaign);

        $campaign = $this->service->close(
            $campaign,
            $request->validated('reason'),
        );

        return CampaignResource::make($campaign);
    }

    public function destroy(Campaign $campaign): Response
    {
        $this->authorize('deleteOrganization', $campaign);

        $this->service->delete($campaign);

        return response()->noContent();
    }

    private function organizationId(): string
    {
        $organizationId = (string) auth()->user()?->organization_id;
        if ($organizationId === '') {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }
}
