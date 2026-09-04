<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\Mobile\PersonalizedFeedService;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationInspectorController extends Controller
{
    public function __invoke(Request $request, PersonalizedFeedService $feed): JsonResponse
    {
        abort_unless(
            $request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::RECOMMENDATION, PermissionAction::VIEW)),
            403,
        );

        $data = $request->validate([
            'userId' => ['required', 'string', 'exists:users,id'],
            'postId' => ['required', 'string', 'exists:posts,id'],
        ]);

        $viewer = User::query()->findOrFail($data['userId']);
        $post = Post::query()
            ->with(['category', 'organization', 'author'])
            ->findOrFail($data['postId']);

        return response()->json(['data' => $feed->inspect($viewer, $post)]);
    }
}
