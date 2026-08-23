<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'size' => (int) $this->size,
            'position' => (int) $this->position,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
