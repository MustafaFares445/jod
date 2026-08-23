<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Data\PostData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\PostRequest;
use App\Http\Requests\Org\PostStatusRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostImage;
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

        $posts = $this->service->paginate(request()->all(), $this->organizationId());

        return PostResource::collection($posts);
    }

    public function store(PostRequest $request): PostResource
    {
        $this->authorize('createOrganization', Post::class);

        $data = collect($request->validated())->except('images')->merge([
            'status' => $request->validated('status', 'published'),
        ])->all();

        $post = $this->service->create(
            PostData::from($data),
            $this->organizationId(),
        );

        $this->storeImages($post, $request->file('images', []));

        return PostResource::make($post->refresh()->load(['campaign', 'images']));
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('viewOrganization', $post);

        return PostResource::make($post->loadMissing(['campaign', 'images']));
    }

    public function update(PostRequest $request, Post $post): PostResource
    {
        $this->authorize('updateOrganization', $post);
        $validated = $request->validated();
        $postData = collect($validated)->except('images')->all();

        if ($postData !== []) {
            $post = $this->service->update(
                $post,
                PostData::from($postData),
                $this->organizationId(),
            );
        }

        if ($request->hasFile('images')) {
            $this->replaceImages($post, $request->file('images', []));
        }

        return PostResource::make($post->refresh()->load(['campaign', 'images']));
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

        return PostResource::make($post->refresh()->loadMissing('images'));
    }

    public function publish(Post $post): PostResource
    {
        $this->authorize('publishOrganization', $post);

        $post = $this->service->publish($post);

        return PostResource::make($post->loadMissing('images'));
    }

    public function archive(Post $post): PostResource
    {
        $this->authorize('archiveOrganization', $post);

        $post = $this->service->archive($post);

        return PostResource::make($post->loadMissing('images'));
    }

    public function restore(Post $post): PostResource
    {
        $this->authorize('restoreOrganization', $post);

        $post = $this->service->restore($post);

        return PostResource::make($post->loadMissing('images'));
    }

    public function destroy(Post $post): Response
    {
        $this->authorize('deleteOrganization', $post);

        $post->loadMissing('images');
        foreach ($post->images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }

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

    /** @param array<int, \Illuminate\Http\UploadedFile> $images */
    private function storeImages(Post $post, array $images): void
    {
        foreach ($images as $position => $image) {
            $path = $image->store("posts/{$post->id}", 'public');

            $post->images()->create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getMimeType(),
                'size' => $image->getSize() ?: 0,
                'position' => $position,
            ]);
        }
    }

    /** @param array<int, \Illuminate\Http\UploadedFile> $images */
    private function replaceImages(Post $post, array $images): void
    {
        $post->loadMissing('images');

        /** @var PostImage $image */
        foreach ($post->images as $image) {
            Storage::disk($image->disk)->delete($image->path);
            $image->delete();
        }

        $this->storeImages($post, $images);
    }
}
