<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Data\PostData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function __construct(private PostService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAnyOrganization', Post::class);

        $posts = $this->service->paginate(request()->all(), $this->organizationId());

        return PostResource::collection($posts);
    }

    public function store(PostRequest $request): PostResource
    {
        $this->authorize('createOrganization', Post::class);

        $post = $this->service->create(
            PostData::from($request->validated()),
            $this->organizationId(),
        );

        return PostResource::make($post);
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('viewOrganization', $post);

        return PostResource::make($post);
    }

    public function update(PostRequest $request, Post $post): PostResource
    {
        $this->authorize('updateOrganization', $post);

        $post = $this->service->update(
            $post,
            PostData::from($request->validated()),
            $this->organizationId(),
        );

        return PostResource::make($post);
    }

    public function publish(Post $post): PostResource
    {
        $this->authorize('publishOrganization', $post);

        $post = $this->service->publish($post);

        return PostResource::make($post);
    }

    public function archive(Post $post): PostResource
    {
        $this->authorize('archiveOrganization', $post);

        $post = $this->service->archive($post);

        return PostResource::make($post);
    }

    public function restore(Post $post): PostResource
    {
        $this->authorize('restoreOrganization', $post);

        $post = $this->service->restore($post);

        return PostResource::make($post);
    }

    public function destroy(Post $post): Response
    {
        $this->authorize('deleteOrganization', $post);

        $this->service->delete($post);

        return response()->noContent();
    }

    private function organizationId(): string
    {
        $organizationId = (string) auth()->user()?->organization_id;
        if ($organizationId === '') {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }
}
