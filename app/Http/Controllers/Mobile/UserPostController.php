<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MyPostRequest;
use App\Http\Requests\Mobile\PostRequest;
use App\Http\Requests\Mobile\PostSubmitRequest;
use App\Http\Resources\Mobile\UserPostResource;
use App\Models\Post;
use App\Services\Mobile\UserPostService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserPostController extends Controller
{
    public function __construct(private readonly UserPostService $service) {}

    /**
     * List the authenticated user's posts.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array<int, array{id: string, ownerId: string|null, title: string|null, details: string|null, city: string|null, type: string, categoryId: string|null, images: list<string>, imageMedia: list<array{id: string, url: string, position: int}>, status: string, rejectionReason: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null}>, error: null, meta: array{currentPage: int, perPage: int, total: int, lastPage: int}}
     */
    public function index(MyPostRequest $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->user(), $request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn (Post $post) => UserPostResource::make($post)->resolve($request)),
            'User posts retrieved successfully.',
        );
    }

    /**
     * Create a draft or submit a new post for review.
     *
     * Requires a Sanctum bearer token. Send images as multipart/form-data when present.
     *
     * @bodyParam images file[] optional Up to five JPEG, PNG, or WebP images, 5 MB each.
     * @response array{success: bool, message: string, data: array{id: string, ownerId: string|null, title: string|null, details: string|null, city: string|null, type: string, categoryId: string|null, images: list<string>, imageMedia: list<array{id: string, url: string, position: int}>, status: string, rejectionReason: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null}, error: null, meta: array{}}
     */
    public function store(PostRequest $request): JsonResponse
    {
        Gate::authorize('createOwn', Post::class);

        $post = $this->service->create($request->user(), $request->validated());
        $message = $request->savesAsDraft() ? 'Draft saved successfully.' : 'Post submitted for review.';

        return MobileApiResponse::success(
            UserPostResource::make($post)->resolve($request),
            $message,
        );
    }

    /**
     * Update the authenticated user's draft or rejected post.
     *
     * Requires a Sanctum bearer token. Manage images through the dedicated post image endpoints.
     *
     * @response array{success: bool, message: string, data: array{id: string, ownerId: string|null, title: string|null, details: string|null, city: string|null, type: string, categoryId: string|null, images: list<string>, imageMedia: list<array{id: string, url: string, position: int}>, status: string, rejectionReason: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null}, error: null, meta: array{}}
     */
    public function update(PostRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('updateOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->update($post, $request->validated()))->resolve($request),
            'Post updated successfully.',
        );
    }

    /**
     * Submit or resubmit the authenticated user's draft or rejected post.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{id: string, ownerId: string|null, title: string|null, details: string|null, city: string|null, type: string, categoryId: string|null, images: list<string>, imageMedia: list<array{id: string, url: string, position: int}>, status: string, rejectionReason: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null}, error: null, meta: array{}}
     */
    public function submit(PostSubmitRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('submitOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->submit($post))->resolve($request),
            'Post submitted for review.',
        );
    }

    /**
     * Archive the authenticated user's active post.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{id: string, ownerId: string|null, title: string|null, details: string|null, city: string|null, type: string, categoryId: string|null, images: list<string>, imageMedia: list<array{id: string, url: string, position: int}>, status: string, rejectionReason: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null}, error: null, meta: array{}}
     */
    public function archive(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('archiveOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->archive($post))->resolve($request),
            'Post archived successfully.',
        );
    }

    /**
     * Repost the authenticated user's archived post.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{id: string, ownerId: string|null, title: string|null, details: string|null, city: string|null, type: string, categoryId: string|null, images: list<string>, imageMedia: list<array{id: string, url: string, position: int}>, status: string, rejectionReason: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null}, error: null, meta: array{}}
     */
    public function repost(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('repostOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->repost($post))->resolve($request),
            'Post reposted successfully.',
        );
    }

    /**
     * Delete the authenticated user's post.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: null, error: null, meta: array{}}
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('deleteOwn', $post);

        $this->service->delete($post);

        return MobileApiResponse::success(null, 'Post deleted successfully.');
    }
}
