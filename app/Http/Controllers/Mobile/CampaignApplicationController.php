<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\CampaignApplicationHistoryRequest;
use App\Http\Requests\Mobile\CampaignApplicationRequest;
use App\Http\Resources\Mobile\CampaignApplicationResource;
use App\Models\CampaignApplication;
use App\Models\User;
use App\Services\Mobile\CampaignApplicationService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignApplicationController extends Controller
{
    public function __construct(private readonly CampaignApplicationService $service) {}

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
        $application = $this->service->apply($this->user($request), $campaign, $request->validated());

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
