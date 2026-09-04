<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\PersonalizationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\CampaignApplicationHistoryRequest;
use App\Http\Requests\Mobile\CampaignApplicationRequest;
use App\Http\Resources\Mobile\CampaignApplicationResource;
use App\Models\CampaignApplication;
use App\Models\User;
use App\Services\Mobile\CampaignApplicationService;
use App\Services\Mobile\InteractionTrackingService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignApplicationController extends Controller
{
    public function __construct(
        private readonly CampaignApplicationService $service,
        private readonly InteractionTrackingService $interactions,
    ) {}

    public function index(CampaignApplicationHistoryRequest $request): JsonResponse
    {
        $paginator = $this->service->paginateForUser($this->user($request), $request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn (CampaignApplication $application) => CampaignApplicationResource::make($application)->resolve($request)),
            'Applications retrieved successfully.',
        );
    }

    public function store(CampaignApplicationRequest $request, string $campaign): JsonResponse
    {
        $user = $this->user($request);
        $application = $this->service->apply($user, $campaign, $request->validated());
        $application->loadMissing('campaign.category');

        if ($application->campaign !== null) {
            $this->interactions->recordCampaignAction(
                $user,
                PersonalizationEventType::VolunteerApplication,
                $application->campaign,
                ['applicationId' => (string) $application->id],
                60 * 24 * 3650,
            );
        }

        return MobileApiResponse::success(
            CampaignApplicationResource::make($application)->resolve($request),
            'Application submitted successfully.',
        );
    }

    public function show(Request $request, string $application): JsonResponse
    {
        $model = $this->service->findForUser($this->user($request), $application);

        if ($model === null) {
            return MobileApiResponse::error('not_found', 'The requested application could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            CampaignApplicationResource::make($model)->resolve($request),
            'Application retrieved successfully.',
        );
    }

    public function destroy(Request $request, string $application): JsonResponse
    {
        $model = $this->service->withdraw($this->user($request), $application);

        if ($model === null) {
            return MobileApiResponse::error('not_found', 'The requested application could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            CampaignApplicationResource::make($model)->resolve($request),
            'Application withdrawn successfully.',
        );
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
