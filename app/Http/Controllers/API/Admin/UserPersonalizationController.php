<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminUserPersonalizationService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPersonalizationController extends Controller
{
    public function __construct(private readonly AdminUserPersonalizationService $service) {}
    public function __invoke(Request $request, User $user): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::PERSONALIZATION, PermissionAction::VIEW);
        return response()->json(['data' => $this->service->summary($user)]);
    }
}
