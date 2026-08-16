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
        $validated = $request->validated();
        $hasCampaignUpdateFields = $this->hasCampaignUpdateFields($validated);

        if ($hasCampaignUpdateFields || ! array_key_exists('status', $validated)) {
            $this->authorize('updateOrganization', $campaign);
        }

        if ($hasCampaignUpdateFields) {
            $campaign = $this->service->update(
                $campaign,
                CampaignData::from($this->campaignData($campaign, $validated)),
            )->refresh();
        }

        if (array_key_exists('status', $validated)) {
            $status = (string) $validated['status'];

            $this->authorize(
                $status === 'closed' ? 'closeOrganization' : 'updateOrganization',
                $campaign,
            );

            $campaign = $this->service->updateStatus(
                $campaign,
                $status,
                $validated['closedReason'] ?? null,
            );
        }

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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasCampaignUpdateFields(array $validated): bool
    {
        return count(array_intersect(array_keys($validated), [
            'title',
            'summary',
            'category',
            'location',
            'goalAmount',
            'beneficiariesCount',
            'startDate',
            'endDate',
        ])) > 0;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function campaignData(Campaign $campaign, array $validated): array
    {
        return [
            'title' => $validated['title'] ?? $campaign->title,
            'summary' => $validated['summary'] ?? $campaign->summary,
            'category' => $validated['category'] ?? $campaign->category,
            'location' => $validated['location'] ?? $campaign->location,
            'goalAmount' => $validated['goalAmount'] ?? $campaign->goal_amount,
            'beneficiariesCount' => $validated['beneficiariesCount'] ?? $campaign->beneficiaries_count,
            'startDate' => $validated['startDate'] ?? $campaign->start_date?->toDateString(),
            'endDate' => $validated['endDate'] ?? $campaign->end_date?->toDateString(),
            'status' => $campaign->status,
        ];
    }
}
