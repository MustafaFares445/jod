<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class GroupCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->relationLoaded('author') ? $this->author : null;
        $group = $this->relationLoaded('post') && $this->post?->relationLoaded('group') ? $this->post->group : null;
        $membership = $group?->relationLoaded('memberships') && $author
            ? $group->memberships->firstWhere('user_id', $author->id)
            : null;

        return [
            'id' => (string) $this->id,
            'postId' => (string) $this->post_id,
            'parentId' => $this->parent_id,
            'author' => $author ? [
                'id' => (string) $author->id,
                'name' => (string) $author->name,
                'username' => filled($author->email) ? Str::before((string) $author->email, '@') : 'jod',
                'avatarUrl' => $author->relationLoaded('avatarMedia') ? $author->avatarMedia?->publicUrl() : null,
                'role' => $membership?->role ?? 'member',
            ] : null,
            'body' => (string) $this->body,
            'createdAt' => $this->created_at?->toIso8601String(),
            'createdAtLabel' => $this->created_at?->diffForHumans() ?? '',
            'likesCount' => (int) $this->likes_count,
            'isLiked' => $this->relationLoaded('likedByUsers') && $this->likedByUsers->isNotEmpty(),
            'replies' => $this->relationLoaded('replies') ? self::collection($this->replies)->resolve($request) : [],
        ];
    }
}
