<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->relationLoaded('media') ? $this->media : $this->resource->media()->get();
        $images = $media->where('prop', 'images')->values();
        $videos = $media->where('prop', 'videos')->values();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'description' => $this->content ?? $this->excerpt,
            'status' => $this->status,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'authorName' => $this->author_name,
            'images' => $images->map(static fn (Media $item): string => $item->publicUrl())->all(),
            'videos' => $videos->map(static fn (Media $item): string => $item->publicUrl())->all(),
            'media' => $media->map(fn (Media $item): array => MediaResource::make($item)->resolve($request))->values()->all(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
