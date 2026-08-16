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
        $email = $author?->email;
        $username = filled($email)
            ? Str::before((string) $email, '@')
            : (Str::slug($name, '.') ?: 'jod');

        return [
            'id' => (string) $this->id,
            'postId' => (string) $this->post_id,
            'body' => $this->body,
            'author' => [
                'id' => (string) ($author?->id ?? $this->user_id),
                'name' => $name,
                'username' => $username,
                'verified' => $author?->email_verified_at !== null,
            ],
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
