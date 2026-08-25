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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function __construct(private PostService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAnyOrganization', Post::class);
        return PostResource::collection($this->service->paginate(request()->all(), $this->organizationId()));
    }

    public function store(PostRequest $request): PostResource
    {
        $this->authorize('createOrganization', Post::class);
        $data = collect($request->validated())->merge(['status' => $request->validated('status', 'published')])->all();
        $post = $this->service->create(PostData::from($data), $this->organizationId());
        $post->update(['author_id' => auth()->id(), 'updated_by' => auth()->id()]);
        return PostResource::make($post->refresh()->load(['campaign', 'images', 'author', 'updatedBy']));
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('viewOrganization', $post);
        return PostResource::make($post->loadMissing(['campaign', 'images', 'author', 'updatedBy']));
    }

    public function update(PostRequest $request, Post $post): PostResource
    {
        $this->authorize('updateOrganization', $post);
        $validated = $request->validated();

        if ($validated !== []) {
            $post = $this->service->update(
                $post,
                PostData::from([
                    'title' => $validated['title'] ?? $post->title,
                    'summary' => $validated['summary'] ?? $post->summary,
                    'type' => $validated['type'] ?? $post->type,
                    'location' => $validated['location'] ?? $post->location,
                    'campaignTitle' => array_key_exists('campaignTitle', $validated) ? $validated['campaignTitle'] : $post->campaign?->title,
                    'status' => $post->status,
                    'audience' => $validated['audience'] ?? $post->audience ?? 'general',
                ]),
                $this->organizationId(),
            );
            $post->update(['updated_by' => auth()->id()]);
        }

        return PostResource::make($post->refresh()->load(['campaign', 'images', 'author', 'updatedBy']));
    }

    public function updateStatus(PostStatusRequest $request, Post $post): PostResource
    {
        $status = (string) $request->validated('status');
        $ability = match ($status) { 'published' => 'publishOrganization', 'archived' => 'archiveOrganization', 'draft' => 'restoreOrganization' };
        $this->authorize($ability, $post);
        $post = $this->service->updateStatus($post, $status);
        return PostResource::make($post->refresh()->loadMissing(['images', 'author', 'updatedBy']));
    }

    public function publish(Post $post): PostResource
    {
        $this->authorize('publishOrganization', $post);
        return PostResource::make($this->service->publish($post)->loadMissing(['images', 'author', 'updatedBy']));
    }

    public function archive(Post $post): PostResource
    {
        $this->authorize('archiveOrganization', $post);
        return PostResource::make($this->service->archive($post)->loadMissing(['images', 'author', 'updatedBy']));
    }

    public function restore(Post $post): PostResource
    {
        $this->authorize('restoreOrganization', $post);
        return PostResource::make($this->service->restore($post)->loadMissing(['images', 'author', 'updatedBy']));
    }

    public function destroy(Post $post): Response
    {
        $this->authorize('deleteOrganization', $post);
        $post->loadMissing('images');
        foreach ($post->images as $media) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }
        $this->service->delete($post);
        return response()->noContent();
    }

    private function organizationId(): string
    {
        $organizationId = (string) auth()->user()?->organization_id;
        if ($organizationId === '') {
            throw ValidationException::withMessages(['organizationId' => ['Authenticated user is not linked to an organization.']]);
        }
        return $organizationId;
    }
}
