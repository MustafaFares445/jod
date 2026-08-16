<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BlogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $author = $this->relationLoaded('author') ? $this->author : null;
        $authorName = (string) ($author?->name ?? $this->author_name ?? 'JOD');
        $authorEmail = $author?->email;
        $username = filled($authorEmail)
            ? Str::before((string) $authorEmail, '@')
            : (Str::slug($authorName, '.') ?: 'jod');

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => (string) ($this->content ?? ''),
            'coverImage' => (string) ($this->cover_image ?? ''),
            'category' => $this->category,
            'readTimeMinutes' => $this->readTimeMinutes(),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'author' => [
                'id' => $author?->id ? (string) $author->id : 'article-author-'.(Str::slug($authorName) ?: 'jod'),
                'name' => $authorName,
                'username' => $username,
                'verified' => $author?->email_verified_at !== null,
            ],
        ];
    }

    private function readTimeMinutes(): int
    {
        $plainText = trim(strip_tags((string) $this->content));
        if ($plainText === '') {
            return 1;
        }

        $words = preg_split('/\s+/u', $plainText, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return max(1, (int) ceil(count($words) / 200));
    }
}
