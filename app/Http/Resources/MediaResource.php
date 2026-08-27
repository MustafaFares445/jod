<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Mobile\MediaOrganizationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model_type->value,
            'modelId' => $this->model_id,
            'prop' => $this->prop,
            'url' => $this->publicUrl(),
            'streamUrl' => $this->prop === 'videos'
                ? route('mobile.discovery.media.stream', ['video' => $this->id])
                : null,
            'originalName' => $this->original_name,
            'description' => $this->description,
            'mimeType' => $this->mime_type,
            'size' => (int) $this->size,
            'position' => (int) $this->position,
            'likesCount' => (int) ($this->reactions_count ?? 0),
            'savesCount' => (int) ($this->saves_count ?? 0),
            'isLiked' => $this->relationLoaded('likes') && $this->likes->isNotEmpty(),
            'isSaved' => $this->relationLoaded('saves') && $this->saves->isNotEmpty(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'organization' => $this->whenLoaded('organization', function () use ($request): ?array {
                if ($this->organization === null) {
                    return null;
                }

                return MediaOrganizationResource::make($this->organization)->resolve($request);
            }),
        ];
    }
}
