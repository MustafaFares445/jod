<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PermissionsController extends Controller
{
    public function __invoke(
        Request $request,
        PermissionCatalogService $permissionCatalogService,
    ): JsonResponse {
        $user = $request->user();

        if ($user->organization_id === null) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        abort_unless($user->isOrganizationOwner(), 403);

        return $this->successResponse($permissionCatalogService->catalog());
    }
}
