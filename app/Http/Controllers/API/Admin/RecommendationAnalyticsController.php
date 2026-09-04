<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecommendationAnalyticsRequest;
use App\Services\Admin\AdminRecommendationAnalyticsService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\JsonResponse;

class RecommendationAnalyticsController extends Controller
{
    public function __construct(private readonly AdminRecommendationAnalyticsService $service) {}
    public function __invoke(RecommendationAnalyticsRequest $request): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::RECOMMENDATION, PermissionAction::VIEW);
        return response()->json(['data' => $this->service->analytics($request->validated())]);
    }
}
