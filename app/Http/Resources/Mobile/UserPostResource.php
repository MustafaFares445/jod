<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPostResource extends JsonResource
{
    /**
     * @return array{id: string, ownerId: string|null, title: string|null, details: string|null, city: string|null, type: string, categoryId: string|null, images: array<int, string>, status: string, rejectionReason: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'ownerId' => $this->author_id ? (string) $this->author_id : null,
            'title' => $this->title,
            'details' => $this->content,
            'city' => $this->location,
            'type' => $this->type,
            'categoryId' => $this->category_id ? (string) $this->category_id : null,
            'images' => [],
            'status' => $this->status === 'published' ? 'active' : $this->status,
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'publishedAt' => $this->published_at?->toISOString(),
        ];
    }
}
