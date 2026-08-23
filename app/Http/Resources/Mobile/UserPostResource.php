<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $imageMedia = $this->relationLoaded('images')
            ? $this->images->map(static fn (Media $image): array => [
                'id' => (string) $image->id,
                'url' => $image->publicUrl(),
                'position' => (int) $image->position,
            ])->values()->all()
            : [];

        return [
            'id' => (string) $this->id,
            'ownerId' => $this->author_id ? (string) $this->author_id : null,
            'title' => $this->title,
            'details' => $this->content,
            'city' => $this->location,
            'type' => $this->type,
            'categoryId' => $this->category_id ? (string) $this->category_id : null,
            'images' => array_column($imageMedia, 'url'),
            'imageMedia' => $imageMedia,
            'viewsCount' => (int) $this->views_count,
            'reactionsCount' => (int) $this->reactions_count,
            'commentsCount' => 0,
            'sharesCount' => 0,
            'stats' => [
                'likes' => (int) $this->reactions_count,
                'comments' => 0,
                'shares' => 0,
            ],
            'status' => in_array($this->status, ['published', 'approved'], true) ? 'active' : $this->status,
            'rejectionReason' => $this->rejection_reason,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'publishedAt' => $this->published_at?->toISOString(),
        ];
    }
}
