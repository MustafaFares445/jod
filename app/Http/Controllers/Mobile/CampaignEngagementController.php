<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\CampaignEngagementService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignEngagementController extends Controller
{
    public function __construct(private readonly CampaignEngagementService $service) {}

    public function like(Request $request, string $campaign): JsonResponse
    {
        try {
            $state = $this->service->like($request->user(), $campaign);
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested campaign could not be found.', null, 404);
        }

        return MobileApiResponse::success($state, 'Campaign liked successfully.');
    }

    public function unlike(Request $request, string $campaign): JsonResponse
    {
        try {
            $state = $this->service->unlike($request->user(), $campaign);
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested campaign could not be found.', null, 404);
        }

        return MobileApiResponse::success($state, 'Campaign unliked successfully.');
    }
}
