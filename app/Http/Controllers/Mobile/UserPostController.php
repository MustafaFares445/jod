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
     * Show one post owned by the authenticated user, including drafts and rejected posts.
     */
    public function show(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('viewOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($post->loadMissing('images'))->resolve($request),
            'User post retrieved successfully.',
        );
    }

    /**
     * Create a draft or submit a new post for review.
     *
     * Media is intentionally not accepted in this request. If images are needed,
     * create the post with saveAsDraft=true, upload each file through the general
     * media manager, then call the submit endpoint.
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
     * Media changes are handled through /api/v1/media/post/{postId}/images.
     */
    public function update(PostRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('updateOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->update($post, $request->validated()))->resolve($request),
            'Post updated successfully.',
        );
    }

    public function submit(PostSubmitRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('submitOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->submit($post))->resolve($request),
            'Post submitted for review.',
        );
    }

    public function archive(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('archiveOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->archive($post))->resolve($request),
            'Post archived successfully.',
        );
    }

    public function repost(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('repostOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($this->service->repost($post))->resolve($request),
            'Post reposted successfully.',
        );
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('deleteOwn', $post);

        $this->service->delete($post);

        return MobileApiResponse::success(null, 'Post deleted successfully.');
    }
}
