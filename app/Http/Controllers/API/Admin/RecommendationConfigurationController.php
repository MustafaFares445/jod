<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecommendationConfigurationRequest;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\RecommendationConfigurationService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationConfigurationController extends Controller
{
    public function __construct(
        private readonly RecommendationConfigurationService $configuration,
        private readonly AdminAuditService $audit,
    ) {}

    public function show(Request $request): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::RECOMMENDATION, PermissionAction::CONFIGURE);
        return response()->json(['data' => $this->payload()]);
    }

    public function update(RecommendationConfigurationRequest $request): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::RECOMMENDATION, PermissionAction::CONFIGURE);
        $actor = $this->actor($request);
        $old = $this->configuration->effective();
        $effective = $this->configuration->update($request->validated());
        $this->audit->record($actor, 'recommendations.config_updated', 'platform_setting', 'recommendation_overrides', $old, $effective);
        return response()->json(['data' => $this->payload()]);
    }

    public function reset(Request $request): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::RECOMMENDATION, PermissionAction::CONFIGURE);
        $actor = $this->actor($request);
        $old = $this->configuration->effective();
        $effective = $this->configuration->reset();
        $this->audit->record($actor, 'recommendations.config_reset', 'platform_setting', 'recommendation_overrides', $old, $effective);
        return response()->json(['data' => $this->payload()]);
    }

    private function payload(): array
    {
        return [
            'defaults' => $this->configuration->defaults(),
            'overrides' => $this->configuration->overrides(),
            'effective' => $this->configuration->effective(),
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }
}
