<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PostCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $author = $this->relationLoaded('user') ? $this->user : null;
        $name = (string) ($author?->name ?? 'JOD User');
        $username = filled($author?->username)
            ? (string) $author->username
            : $this->derivedUsername($author?->email, $name);

        $authorData = [
            'id' => (string) ($author?->id ?? $this->user_id),
            'name' => $name,
            'username' => $username,
            'verified' => $author?->email_verified_at !== null,
        ];

        if ($author !== null && ($avatarUrl = $author->avatarUrl()) !== null) {
            $authorData['avatarUrl'] = $avatarUrl;
        }

        return [
            'id' => (string) $this->id,
            'postId' => (string) $this->post_id,
            'body' => $this->body,
            'author' => $authorData,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function derivedUsername(?string $email, string $name): string
    {
        if (filled($email)) {
            return Str::before((string) $email, '@');
        }

        return Str::slug($name, '.') ?: 'jod';
    }
}
