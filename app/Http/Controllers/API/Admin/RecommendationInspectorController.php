<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminRecommendationInspectorService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationInspectorController extends Controller
{
    public function __construct(private readonly AdminRecommendationInspectorService $service) {}
    public function __invoke(Request $request, User $user): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::RECOMMENDATION, PermissionAction::DIAGNOSTICS);
        $limit = max(1, min((int) $request->integer('limit', 20), 50));
        return response()->json(['data' => $this->service->preview($user, $limit)]);
    }
}
