<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Me;

use App\Http\Controllers\Controller;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    public function __invoke(
        Request $request,
        PermissionCatalogService $permissionCatalogService,
    ): array {
        return [
            'data' => $permissionCatalogService->forUser($request->user()),
        ];
    }
}
