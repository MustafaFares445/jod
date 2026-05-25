<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Data\PostData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function __construct(private PostService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorizeOrgPermission('org.posts.view');

        $posts = $this->service->paginate(request()->all(), $this->organizationId());

        return PostResource::collection($posts);
    }

    public function store(PostRequest $request): PostResource
    {
        $this->authorizeOrgPermission('org.posts.create');

        $post = $this->service->create(
            PostData::from($request->validated()),
            $this->organizationId(),
        );

        return PostResource::make($post);
    }

    public function show(Post $post): PostResource
    {
        $this->authorizeOrgPermission('org.posts.view');
        $this->assertSameOrganization((int) $post->organization_id);

        return PostResource::make($post);
    }

    public function update(PostRequest $request, Post $post): PostResource
    {
        $this->authorizeOrgPermission('org.posts.update');
        $this->assertSameOrganization((int) $post->organization_id);

        $post = $this->service->update(
            $post,
            PostData::from($request->validated()),
            $this->organizationId(),
        );

        return PostResource::make($post);
    }

    public function publish(Post $post): PostResource
    {
        $this->authorizeOrgPermission('org.posts.update');
        $this->assertSameOrganization((int) $post->organization_id);

        $post = $this->service->publish($post);

        return PostResource::make($post);
    }

    public function archive(Post $post): PostResource
    {
        $this->authorizeOrgPermission('org.posts.update');
        $this->assertSameOrganization((int) $post->organization_id);

        $post = $this->service->archive($post);

        return PostResource::make($post);
    }

    public function restore(Post $post): PostResource
    {
        $this->authorizeOrgPermission('org.posts.update');
        $this->assertSameOrganization((int) $post->organization_id);

        $post = $this->service->restore($post);

        return PostResource::make($post);
    }

    public function destroy(Post $post): Response
    {
        $this->authorizeOrgPermission('org.posts.delete');
        $this->assertSameOrganization((int) $post->organization_id);

        $this->service->delete($post);

        return response()->noContent();
    }

    private function organizationId(): int
    {
        $organizationId = (int) auth()->user()?->organization_id;
        if ($organizationId <= 0) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }

    private function authorizeOrgPermission(string $permission): void
    {
        if (!auth()->user()?->can($permission)) {
            throw new AuthorizationException();
        }
    }

    private function assertSameOrganization(int $organizationId): void
    {
        if ($organizationId !== $this->organizationId()) {
            throw new AuthorizationException();
        }
    }
}
