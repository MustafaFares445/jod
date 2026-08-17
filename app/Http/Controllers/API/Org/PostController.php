<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Data\PostData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\PostRequest;
use App\Http\Requests\Org\PostStatusRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        $validated = $request->validated();

        return DB::transaction(function () use ($post, $validated): PostResource {
            $requestedStatus = array_key_exists('status', $validated)
                ? (string) $validated['status']
                : null;
            $type = (string) ($validated['type'] ?? $post->type);
            $campaignRelatedTypes = ['campaign_teaser', 'campaign_update', 'campaign_summary'];
            $campaignTitle = array_key_exists('campaignTitle', $validated)
                ? $validated['campaignTitle']
                : (in_array($type, $campaignRelatedTypes, true) ? $post->campaign?->title : null);

            $post = $this->service->update(
                $post,
                PostData::from([
                    'title' => $validated['title'] ?? $post->title,
                    'summary' => $validated['summary'] ?? $post->summary,
                    'type' => $type,
                    'status' => $post->status,
                    'authorName' => $validated['authorName'] ?? $post->author_name,
                    'location' => $validated['location'] ?? $post->location,
                    'campaignTitle' => $campaignTitle,
                ]),
                $this->organizationId(),
            );

            if ($requestedStatus !== null && $requestedStatus !== $post->status) {
                $ability = match ($requestedStatus) {
                    'published' => 'publishOrganization',
                    'archived' => 'archiveOrganization',
                    'draft' => 'restoreOrganization',
                };

                $this->authorize($ability, $post);
                $post = $this->service->updateStatus($post, $requestedStatus);
            }

            return PostResource::make($post->refresh());
        });
    }

    public function updateStatus(PostStatusRequest $request, Post $post): PostResource
    {
        $status = (string) $request->validated('status');

        $ability = match ($status) {
            'published' => 'publishOrganization',
            'archived' => 'archiveOrganization',
            'draft' => 'restoreOrganization',
        };

        $this->authorize($ability, $post);

        $post = $this->service->updateStatus($post, $status);

        return PostResource::make($post->refresh());
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
