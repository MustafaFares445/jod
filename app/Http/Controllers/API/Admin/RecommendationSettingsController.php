<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AuditLogService;
use App\Services\RecommendationSettingsService;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationSettingsController extends Controller
{
    public function __construct(
        private readonly RecommendationSettingsService $settings,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, PermissionAction::VIEW);

        return response()->json(['data' => $this->payload()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAction($request, PermissionAction::UPDATE);

        $data = $request->validate([
            'weights' => ['sometimes', 'array'],
            'weights.*' => ['numeric', 'min:-200', 'max:200'],
            'candidateLimit' => ['sometimes', 'integer', 'min:20', 'max:500'],
            'popularityCap' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'explorationRatio' => ['sometimes', 'numeric', 'min:0', 'max:0.5'],
        ]);

        $unknownKeys = array_values(array_diff(
            array_keys((array) ($data['weights'] ?? [])),
            $this->settings->activeWeightKeys(),
        ));
        if ($unknownKeys !== []) {
            return response()->json([
                'message' => 'Unknown recommendation weight keys.',
                'errors' => ['weights' => ['Unknown keys: '.implode(', ', $unknownKeys)]],
            ], 422);
        }

        $before = $this->settings->all();
        $after = $this->settings->update($data);

        $this->audit->log(
            (string) $request->user()->id,
            'recommendation.settings.updated',
            'RecommendationSettings',
            'global',
            ['before' => $before, 'after' => $after],
        );

        return response()->json([
            'data' => $this->payload(),
            'message' => 'Recommendation settings updated successfully.',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $record = PlatformSetting::query()
            ->where('key', RecommendationSettingsService::KEY)
            ->first();

        return [
            ...$this->settings->all(),
            'activeWeightKeys' => $this->settings->activeWeightKeys(),
            'updatedAt' => $record?->updated_at?->toIso8601String(),
        ];
    }

    private function authorizeAction(Request $request, PermissionAction $action): void
    {
        abort_unless(
            $request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::RECOMMENDATION, $action)),
            403,
        );
    }
}
