<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'severity' => $this->severity,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'organizationName' => $this->organization?->name,
            'reporterName' => $this->reporter?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
                'email' => $this->assignee?->email,
            ]),
            'timeline' => $this->timeline ?? [],
            'evidence' => $this->evidence ?? [],
            'closedAt' => $this->closed_at?->toIso8601String(),
        ];
    }
}
