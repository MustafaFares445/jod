<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaReportResource extends JsonResource
{
    /** @return array{id: string, mediaId: string|null, status: string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'mediaId' => $this->entity_id ? (string) $this->entity_id : null,
            'status' => (string) $this->status,
        ];
    }
}
