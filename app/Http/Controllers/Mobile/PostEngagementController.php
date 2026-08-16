<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\EngagementStateResource;
use App\Services\Mobile\PostEngagementService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostEngagementController extends Controller
{
    public function __construct(private readonly PostEngagementService $service) {}

    /**
     * Like a public active post.
     *
     * Requires a Sanctum bearer token. Repeated calls are idempotent.
     *
     * @urlParam post string required The post identifier.
     *
     * @response array{success: bool, message: string, data: array{postId: string, isLiked: bool, likesCount: int}, error: null, meta: array{}}
     */
    public function like(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->like($request->user(), $post);
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post liked successfully.',
        );
    }

    /**
     * Unlike a public active post.
     *
     * Requires a Sanctum bearer token. Repeated calls are idempotent.
     *
     * @urlParam post string required The post identifier.
     *
     * @response array{success: bool, message: string, data: array{postId: string, isLiked: bool, likesCount: int}, error: null, meta: array{}}
     */
    public function unlike(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->unlike($request->user(), $post);
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post unliked successfully.',
        );
    }

    /**
     * Save a public active post.
     *
     * Requires a Sanctum bearer token. Repeated calls are idempotent.
     *
     * @urlParam post string required The post identifier.
     *
     * @response array{success: bool, message: string, data: array{postId: string, isSaved: bool, savesCount: int}, error: null, meta: array{}}
     */
    public function save(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->save($request->user(), $post);
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post saved successfully.',
        );
    }

    /**
     * Unsave a public active post.
     *
     * Requires a Sanctum bearer token. Repeated calls are idempotent.
     *
     * @urlParam post string required The post identifier.
     *
     * @response array{success: bool, message: string, data: array{postId: string, isSaved: bool, savesCount: int}, error: null, meta: array{}}
     */
    public function unsave(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->unsave($request->user(), $post);
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post removed from saved posts.',
        );
    }
}
