<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Admin\AdminHelpMatchingService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpMatchingController extends Controller
{
    public function __construct(private readonly AdminHelpMatchingService $service) {}
    public function index(Request $request)
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_MATCHING, PermissionAction::VIEW);
        $filters = $request->validate(['status' => ['nullable', 'string'], 'urgency' => ['nullable', 'string'], 'categoryId' => ['nullable', 'uuid'], 'city' => ['nullable', 'string', 'max:120']]);
        return response()->json($this->service->paginate($filters, max(1, min((int) $request->integer('perPage', 20), 100))));
    }
    public function show(Request $request, Post $post): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_MATCHING, PermissionAction::VIEW);
        return response()->json(['data' => $this->service->detail($post)]);
    }
}
