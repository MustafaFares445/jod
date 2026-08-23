<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    private const RELATIONS = [
        'organization',
        'campaign',
        'images',
        'author',
        'updatedBy',
        'reviewedBy',
        'approvedBy',
        'rejectedBy',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Post::class);

        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $search = trim((string) data_get($request->query(), 'filter.search', ''));
        $status = data_get($request->query(), 'filter.status');

        $posts = Post::query()
            ->with(self::RELATIONS)
            ->whereNull('organization_id')
            ->whereHas('author', fn (Builder $query) => $query->where('user_type', 'admin'))
            ->when($status && $status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($perPage);

        return PostResource::collection($posts);
    }

    public function store(AdminPostRequest $request): PostResource
    {
        $this->authorize('createAdmin', Post::class);

        $data = $request->validated();
        $content = (string) ($data['content'] ?? $data['description']);
        $status = (string) ($data['status'] ?? 'published');
        $actorId = (string) $request->user()->id;

        $post = Post::query()->create([
            'title' => $data['title'],
            'summary' => mb_substr($content, 0, 255),
            'content' => $content,
            'type' => 'general',
            'status' => $status,
            'author_id' => $actorId,
            'updated_by' => $actorId,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        return PostResource::make($post->load(self::RELATIONS));
    }

    public function show(Post $post): PostResource
    {
        $post->loadMissing('author');
        $this->authorize('viewAdmin', $post);

        return PostResource::make($post->loadMissing(self::RELATIONS));
    }

    public function update(AdminPostRequest $request, Post $post): PostResource
    {
        $post->loadMissing('author');
        $this->authorize('updateAdmin', $post);

        $data = $request->validated();
        $updates = ['updated_by' => (string) $request->user()->id];

        if (array_key_exists('title', $data)) {
            $updates['title'] = $data['title'];
        }

        if (array_key_exists('content', $data) || array_key_exists('description', $data)) {
            $content = (string) ($data['content'] ?? $data['description']);
            $updates['content'] = $content;
            $updates['summary'] = mb_substr($content, 0, 255);
        }

        if (array_key_exists('status', $data)) {
            $updates['status'] = $data['status'];
            $updates['published_at'] = $data['status'] === 'published'
                ? ($post->published_at ?? now())
                : null;
        }

        $post->update($updates);

        return PostResource::make($post->refresh()->load(self::RELATIONS));
    }

    public function destroy(Post $post): Response
    {
        $post->loadMissing(['author', 'images']);
        $this->authorize('deleteAdmin', $post);

        foreach ($post->images as $media) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();
        }

        $post->delete();

        return response()->noContent();
    }
}
