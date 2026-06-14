<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\JsonResponse;

class PermissionsController extends Controller
{
    public function __invoke(PermissionCatalogService $permissionCatalogService): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $permissionCatalogService->catalog(),
        ]);
    }
}
