<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Data\CampaignData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\CampaignRequest;
use App\Http\Requests\Org\CampaignStatusRequest;
use App\Http\Requests\Org\CloseCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        $validated = $request->validated();

        return DB::transaction(function () use ($campaign, $validated): CampaignResource {
            $requestedStatus = array_key_exists('status', $validated)
                ? (string) $validated['status']
                : null;

            $campaign = $this->service->update(
                $campaign,
                CampaignData::from([
                    'title' => $validated['title'] ?? $campaign->title,
                    'summary' => $validated['summary'] ?? $campaign->summary,
                    'category' => $validated['category'] ?? $campaign->category,
                    'status' => $campaign->status,
                    'location' => $validated['location'] ?? $campaign->location,
                    'goalAmount' => $validated['goalAmount'] ?? (float) $campaign->goal_amount,
                    'beneficiariesCount' => $validated['beneficiariesCount'] ?? (int) $campaign->beneficiaries_count,
                    'startDate' => $validated['startDate'] ?? $campaign->start_date?->toDateString(),
                    'endDate' => $validated['endDate'] ?? $campaign->end_date?->toDateString(),
                ]),
            );

            if ($requestedStatus !== null && $requestedStatus !== $campaign->status) {
                if ($requestedStatus === 'closed') {
                    $this->authorize('closeOrganization', $campaign);
                }

                $campaign = $this->service->updateStatus($campaign, $requestedStatus);
            }

            return CampaignResource::make($campaign->refresh());
        });
    }

    public function updateStatus(CampaignStatusRequest $request, Campaign $campaign): CampaignResource
    {
        $status = (string) $request->validated('status');

        $this->authorize(
            $status === 'closed' ? 'closeOrganization' : 'updateOrganization',
            $campaign,
        );

        $campaign = $this->service->updateStatus(
            $campaign,
            $status,
            $request->validated('closedReason'),
        );

        return CampaignResource::make($campaign->refresh());
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
