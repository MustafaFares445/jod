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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorizeOrgPermission('org.campaigns.view');

        $campaigns = $this->service->paginate(request()->all(), $this->organizationId());

        return CampaignResource::collection($campaigns);
    }

    public function store(CampaignRequest $request): CampaignResource
    {
        $this->authorizeOrgPermission('org.campaigns.create');

        $campaign = $this->service->create(
            CampaignData::from($request->validated()),
            $this->organizationId(),
        );

        return CampaignResource::make($campaign);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorizeOrgPermission('org.campaigns.view');
        $this->assertSameOrganization((int) $campaign->organization_id);

        return CampaignResource::make($campaign);
    }

    public function update(CampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorizeOrgPermission('org.campaigns.update');
        $this->assertSameOrganization((int) $campaign->organization_id);

        $campaign = $this->service->update(
            $campaign,
            CampaignData::from($request->validated()),
        );

        return CampaignResource::make($campaign);
    }

    public function close(CloseCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorizeOrgPermission('org.campaigns.update');
        $this->assertSameOrganization((int) $campaign->organization_id);

        $campaign = $this->service->close(
            $campaign,
            $request->validated('reason'),
        );

        return CampaignResource::make($campaign);
    }

    public function destroy(Campaign $campaign): Response
    {
        $this->authorizeOrgPermission('org.campaigns.delete');
        $this->assertSameOrganization((int) $campaign->organization_id);

        $this->service->delete($campaign);

        return response()->noContent();
    }

    private function organizationId(): int
    {
        $organizationId = (int) auth()->user()?->organization_id;
        if ($organizationId <= 0) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }

    private function authorizeOrgPermission(string $permission): void
    {
        if (!auth()->user()?->can($permission)) {
            throw new AuthorizationException();
        }
    }

    private function assertSameOrganization(int $organizationId): void
    {
        if ($organizationId !== $this->organizationId()) {
            throw new AuthorizationException();
        }
    }
}
