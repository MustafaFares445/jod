<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Data\CampaignData;
use App\Enums\NotificationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\CampaignRequest;
use App\Http\Requests\Org\CloseCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\CampaignService;
use App\Services\NotificationEventService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function __construct(
        private CampaignService $service,
        private readonly NotificationEventService $notifications,
    ) {}

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
            CampaignData::from(collect($request->validated())->merge([
                'status' => $request->validated('status', 'active'),
            ])->all()),
            $this->organizationId(),
        );

        return CampaignResource::make($campaign->refresh()->load(['imageMedia', 'categoryRelation']));
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorize('viewOrganization', $campaign);

        return CampaignResource::make($campaign->loadMissing(['imageMedia', 'categoryRelation']));
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
            $this->authorize($status === 'closed' ? 'closeOrganization' : 'updateOrganization', $campaign);
            $wasClosed = $campaign->status === 'closed';
            $campaign = $this->service->updateStatus($campaign, $status, $validated['closedReason'] ?? null);

            if ($status === 'closed' && ! $wasClosed) {
                $this->notifyCampaignClosed($campaign);
            }
        }

        return CampaignResource::make($campaign->refresh()->load(['imageMedia', 'categoryRelation']));
    }

    public function close(CloseCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('closeOrganization', $campaign);
        $campaign = $this->service->close($campaign, $request->validated('reason'));
        $this->notifyCampaignClosed($campaign);

        return CampaignResource::make($campaign->loadMissing(['imageMedia', 'categoryRelation']));
    }

    public function destroy(Campaign $campaign): Response
    {
        $this->authorize('deleteOrganization', $campaign);
        $campaign->loadMissing('media');

        foreach ($campaign->media as $media) {
            \Illuminate\Support\Facades\Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }

        $this->service->delete($campaign);

        return response()->noContent();
    }

    private function notifyCampaignClosed(Campaign $campaign): void
    {
        $creatorId = auth()->id() !== null ? (string) auth()->id() : null;
        $reason = filled($campaign->closed_reason) ? ' السبب: '.$campaign->closed_reason : '';
        $body = "تم إغلاق حملة {$campaign->title}.{$reason}";

        $this->notifications->notifyOrganization(
            (string) $campaign->organization_id,
            NotificationEventType::CampaignClosed,
            'تم إغلاق الحملة',
            $body,
            'campaign',
            'normal',
            $campaign->title,
            '/org/campaigns/'.$campaign->id,
            $creatorId,
        );

        $this->notifications->notifyCampaignParticipants(
            $campaign,
            NotificationEventType::CampaignClosed,
            'انتهت الحملة',
            $body,
            'campaign',
            'normal',
            $campaign->title,
            '/campaigns/'.$campaign->id,
            $creatorId,
        );
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

    /** @param array<string, mixed> $validated */
    private function hasCampaignUpdateFields(array $validated): bool
    {
        return count(array_intersect(array_keys($validated), [
            'title', 'summary', 'category', 'categoryId', 'location', 'goalAmount', 'beneficiariesCount', 'startDate', 'endDate',
        ])) > 0;
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function campaignData(Campaign $campaign, array $validated): array
    {
        return [
            'title' => $validated['title'] ?? $campaign->title,
            'summary' => $validated['summary'] ?? $campaign->summary,
            'category' => $validated['category'] ?? $campaign->category,
            'categoryId' => $validated['categoryId'] ?? $campaign->category_id,
            'location' => $validated['location'] ?? $campaign->location,
            'goalAmount' => $validated['goalAmount'] ?? $campaign->goal_amount,
            'beneficiariesCount' => $validated['beneficiariesCount'] ?? $campaign->beneficiaries_count,
            'startDate' => $validated['startDate'] ?? $campaign->start_date?->toDateString(),
            'endDate' => $validated['endDate'] ?? $campaign->end_date?->toDateString(),
            'status' => $campaign->status,
        ];
    }
}
