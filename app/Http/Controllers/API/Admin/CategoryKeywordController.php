<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Services\Admin\AdminCategoryKeywordService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryKeywordController extends Controller
{
    public function __construct(private readonly AdminCategoryKeywordService $service) {}

    public function index(Request $request, Category $category): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CATEGORY, PermissionAction::VIEW);
        return response()->json(['data' => ['categoryId' => (string) $category->id, 'keywords' => $this->service->keywords($category)]]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CATEGORY, PermissionAction::UPDATE);
        $data = $request->validate([
            'keywords' => ['required', 'array', 'max:50'],
            'keywords.*' => ['required', 'string', 'max:150'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return response()->json([
            'data' => [
                'categoryId' => (string) $category->id,
                'keywords' => $this->service->replace($actor, $category, $data['keywords']),
            ],
        ]);
    }
}
