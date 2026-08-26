<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\PostImageReorderRequest;
use App\Http\Requests\Mobile\PostImageUploadRequest;
use App\Http\Resources\Mobile\UserPostResource;
use App\Models\Post;
use App\Services\Mobile\PostImageService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostImageController extends Controller
{
    public function __construct(private readonly PostImageService $service) {}

    /**
     * Add images to an owned draft or rejected post.
     *
     * Send as multipart/form-data. A post may contain at most ten images total.
     *
     * @urlParam post string required The post identifier.
     * @bodyParam images file[] required JPEG, PNG, or WebP images, up to 5 MB each.
     */
    public function store(PostImageUploadRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('updateOwn', $post);

        /** @var list<\Illuminate\Http\UploadedFile> $images */
        $images = $request->file('images', []);
        $post = $this->service->add($post, $images);

        return MobileApiResponse::success(
            UserPostResource::make($post)->resolve($request),
            'Post images added successfully.',
        );
    }

    /**
     * Reorder every image on an owned draft or rejected post.
     *
     * @urlParam post string required The post identifier.
     * @bodyParam imageIds string[] required Every image identifier for the post in the desired order.
     */
    public function reorder(PostImageReorderRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('updateOwn', $post);

        /** @var list<string> $imageIds */
        $imageIds = $request->validated('imageIds');
        $post = $this->service->reorder($post, $imageIds);

        return MobileApiResponse::success(
            UserPostResource::make($post)->resolve($request),
            'Post images reordered successfully.',
        );
    }

    /**
     * Delete one image from an owned draft or rejected post.
     *
     * @urlParam post string required The post identifier.
     * @urlParam image string required The image identifier.
     */
    public function destroy(Request $request, Post $post, string $image): JsonResponse
    {
        Gate::authorize('updateOwn', $post);

        $updatedPost = $this->service->delete($post, $image);

        if ($updatedPost === null) {
            return MobileApiResponse::error('not_found', 'The requested post image could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            UserPostResource::make($updatedPost)->resolve($request),
            'Post image deleted successfully.',
        );
    }
}
