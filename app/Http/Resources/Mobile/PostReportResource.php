<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostReportResource extends JsonResource
{
    /**
     * @return array{id: string, postId: string|null, status: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'postId' => $this->entity_id ? (string) $this->entity_id : null,
            'status' => $this->status,
        ];
    }
}
