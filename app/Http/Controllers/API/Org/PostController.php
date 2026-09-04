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
        return PostResource::make($this->loadPost($post->refresh()));
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('viewOrganization', $post);
        return PostResource::make($this->loadPost($post));
    }

    public function update(PostRequest $request, Post $post): PostResource
    {
        $this->authorize('updateOrganization', $post);
        $validated = $request->validated();
        if ($validated !== []) {
            $post = $this->service->update($post, PostData::from([
                'title' => $validated['title'] ?? $post->title,
                'summary' => $validated['summary'] ?? $post->summary,
                'type' => $validated['type'] ?? $post->type,
                'location' => $validated['location'] ?? $post->location,
                'campaignTitle' => array_key_exists('campaignTitle', $validated) ? $validated['campaignTitle'] : $post->campaign?->title,
                'status' => $post->status,
                'audience' => $validated['audience'] ?? $post->audience ?? 'general',
                'categoryId' => $validated['categoryId'] ?? $post->category_id,
                'urgency' => $validated['urgency'] ?? ($post->urgency?->value ?? $post->urgency ?? 'normal'),
                'urgencyReason' => array_key_exists('urgencyReason', $validated) ? $validated['urgencyReason'] : $post->urgency_reason,
                'expiresAt' => array_key_exists('expiresAt', $validated) ? $validated['expiresAt'] : $post->expires_at?->toIso8601String(),
                'requiredCapabilityIds' => array_key_exists('requiredCapabilityIds', $validated)
                    ? $validated['requiredCapabilityIds']
                    : $post->requiredCapabilities()->pluck('capabilities.id')->all(),
            ]), $this->organizationId());
            $post->update(['updated_by' => auth()->id()]);
        }
        return PostResource::make($this->loadPost($post->refresh()));
    }

    public function updateStatus(PostStatusRequest $request, Post $post): PostResource
    {
        $status = (string) $request->validated('status');
        $this->authorize($status === 'published' ? 'publishOrganization' : 'updateOrganization', $post);
        $post = $this->service->updateStatus($post, $status);
        return PostResource::make($this->loadPost($post->refresh()));
    }

    public function publish(Post $post): PostResource
    {
        $this->authorize('publishOrganization', $post);
        return PostResource::make($this->loadPost($this->service->publish($post)));
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

    private function loadPost(Post $post): Post
    {
        return $post->loadMissing(['organization', 'campaign', 'category', 'requiredCapabilities', 'images', 'videos', 'author', 'updatedBy', 'urgencyReviewedBy']);
    }

    private function organizationId(): string
    {
        $organizationId = (string) auth()->user()?->organization_id;
        if ($organizationId === '') throw ValidationException::withMessages(['organizationId' => ['Authenticated user is not linked to an organization.']]);
        return $organizationId;
    }
}
