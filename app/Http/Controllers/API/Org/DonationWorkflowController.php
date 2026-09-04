<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Enums\DonationStatus;
use App\Enums\PersonalizationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\DonationCancelRequest;
use App\Http\Resources\DonorResource;
use App\Models\Donation;
use App\Models\User;
use App\Services\Mobile\DonationService;
use App\Services\Mobile\InteractionTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class DonationWorkflowController extends Controller
{
    public function __construct(
        private readonly DonationService $service,
        private readonly InteractionTrackingService $interactions,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Donation::class);
        $validated = validator($request->query(), [
            'status' => ['sometimes', 'string', Rule::in(['pending', 'contacting', 'agreed', 'completed', 'cancelled'])],
            'campaignId' => ['sometimes', 'string', 'exists:campaigns,id'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $query = Donation::query()
            ->where('organization_id', $this->user($request)->organization_id)
            ->with('campaign.organization')
            ->when(isset($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->when(isset($validated['campaignId']), fn ($q) => $q->where('campaign_id', $validated['campaignId']))
            ->orderByDesc('created_at');

        return DonorResource::collection($query->paginate((int) ($validated['perPage'] ?? 20)));
    }

    public function show(Request $request, Donation $donation): DonorResource
    {
        $this->authorize('view', $donation);
        return DonorResource::make($donation->load('campaign.organization'));
    }

    public function contact(Request $request, string $donation): DonorResource
    {
        return DonorResource::make($this->service->markContacting($this->user($request), $donation));
    }

    public function agree(Request $request, string $donation): DonorResource
    {
        return DonorResource::make($this->service->markAgreed($this->user($request), $donation));
    }

    public function complete(Request $request, string $donation): DonorResource
    {
        $before = Donation::query()->whereKey($donation)->first();
        $wasCompleted = ($before?->status?->value ?? $before?->status) === DonationStatus::Completed->value;

        $completed = $this->service->complete($this->user($request), $donation);
        $completed->loadMissing(['campaign.category', 'creator']);

        if (! $wasCompleted && $completed->creator !== null && $completed->campaign !== null) {
            $this->interactions->recordCampaignAction(
                $completed->creator,
                PersonalizationEventType::CampaignDonation,
                $completed->campaign,
                ['donationId' => (string) $completed->id],
            );
        }

        return DonorResource::make($completed);
    }

    public function cancel(DonationCancelRequest $request, string $donation): DonorResource
    {
        return DonorResource::make($this->service->cancel($this->user($request), $donation, (string) $request->validated('reason')));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        return $user;
    }
}
