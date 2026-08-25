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

    public function index(MyPostRequest $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->user(), $request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn (Post $post) => UserPostResource::make($post)->resolve($request)),
            'User posts retrieved successfully.',
        );
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('viewOwn', $post);

        return MobileApiResponse::success(
            UserPostResource::make($post->loadMissing('images'))->resolve($request),
            'User post retrieved successfully.',
        );
    }

    public function store(PostRequest $request): JsonResponse
    {
        Gate::authorize('createOwn', Post::class);

        $validated = $request->validated();
        $post = $this->service->create($request->user(), $validated);
        if (array_key_exists('audience', $validated)) {
            $post->update(['audience' => $validated['audience']]);
            $post->refresh()->loadMissing('images');
        }
        $message = $request->savesAsDraft() ? 'Draft saved successfully.' : 'Post submitted for review.';

        return MobileApiResponse::success(UserPostResource::make($post)->resolve($request), $message);
    }

    public function update(PostRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('updateOwn', $post);

        $validated = $request->validated();
        $post = $this->service->update($post, $validated);
        if (array_key_exists('audience', $validated)) {
            $post->update(['audience' => $validated['audience']]);
            $post->refresh()->loadMissing('images');
        }

        return MobileApiResponse::success(
            UserPostResource::make($post)->resolve($request),
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
