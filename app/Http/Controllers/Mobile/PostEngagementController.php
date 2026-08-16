<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\PostShareRequest;
use App\Http\Resources\Mobile\EngagementStateResource;
use App\Services\Mobile\PostEngagementService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostEngagementController extends Controller
{
    public function __construct(private readonly PostEngagementService $service) {}

    public function like(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->like($request->user(), $post);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post liked successfully.',
        );
    }

    public function unlike(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->unlike($request->user(), $post);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post unliked successfully.',
        );
    }

    public function save(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->save($request->user(), $post);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post saved successfully.',
        );
    }

    public function unsave(Request $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->unsave($request->user(), $post);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post removed from saved posts.',
        );
    }

    public function share(PostShareRequest $request, string $post): JsonResponse
    {
        try {
            $state = $this->service->share(
                $request->user(),
                $post,
                $request->validated('channel'),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            EngagementStateResource::make($state)->resolve($request),
            'Post share recorded successfully.',
        );
    }

    private function notFound(): JsonResponse
    {
        return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
    }
}
