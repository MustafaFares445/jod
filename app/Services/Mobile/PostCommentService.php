<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Notification;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PostCommentService
{
    /**
     * @param  array{page?: int, perPage?: int, sort?: string}  $params
     */
    public function paginate(string $postId, array $params): LengthAwarePaginator
    {
        $this->findPublicPost($postId);
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = (string) ($params['sort'] ?? 'newest');

        $query = PostComment::query()
            ->with('user')
            ->where('post_id', $postId);

        if ($sort === 'oldest') {
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        return $query->paginate($perPage);
    }

    public function create(User $user, string $postId, string $body): PostComment
    {
        return DB::transaction(function () use ($user, $postId, $body): PostComment {
            $post = $this->findPublicPostForUpdate($postId);

            $comment = PostComment::query()->create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'body' => trim($body),
            ]);

            $this->syncCommentCount($post);
            $this->notifyPostAuthor($post, $comment, $user);

            return $comment->load('user');
        });
    }

    public function update(User $user, string $postId, string $commentId, string $body): ?PostComment
    {
        $this->findPublicPost($postId);

        $comment = PostComment::query()
            ->where('post_id', $postId)
            ->where('user_id', $user->id)
            ->whereKey($commentId)
            ->first();

        if ($comment === null) {
            return null;
        }

        $comment->update(['body' => trim($body)]);

        return $comment->refresh()->load('user');
    }

    public function delete(User $user, string $postId, string $commentId): bool
    {
        return DB::transaction(function () use ($user, $postId, $commentId): bool {
            $post = $this->findPublicPostForUpdate($postId);
            $comment = PostComment::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->whereKey($commentId)
                ->lockForUpdate()
                ->first();

            if ($comment === null) {
                return false;
            }

            $comment->delete();
            $this->syncCommentCount($post);

            return true;
        });
    }

    private function findPublicPost(string $postId): Post
    {
        return Post::query()
            ->whereKey($postId)
            ->where('status', 'published')
            ->firstOr(fn () => throw new ModelNotFoundException);
    }

    private function findPublicPostForUpdate(string $postId): Post
    {
        return Post::query()
            ->whereKey($postId)
            ->where('status', 'published')
            ->lockForUpdate()
            ->firstOr(fn () => throw new ModelNotFoundException);
    }

    private function syncCommentCount(Post $post): void
    {
        $post->update([
            'comments_count' => PostComment::query()
                ->where('post_id', $post->id)
                ->count(),
        ]);
    }

    private function notifyPostAuthor(Post $post, PostComment $comment, User $commenter): void
    {
        if ($post->author_id === null || (string) $post->author_id === (string) $commenter->id) {
            return;
        }

        Notification::query()->create([
            'title' => 'تعليق جديد على منشورك',
            'body' => $commenter->name.' علّق على منشورك.',
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'post',
            'recipient_scope' => 'users',
            'priority' => 'normal',
            'reference_label' => 'عرض التعليق',
            'reference_path' => '/posts/'.$post->id.'?comment='.$comment->id,
            'organization_id' => $post->organization_id,
            'creator_id' => $commenter->id,
            'recipient_id' => $post->author_id,
            'sent_at' => now(),
        ]);
    }
}
