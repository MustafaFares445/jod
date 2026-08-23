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
            'entity' => $this->reportedEntity($request),
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

    /** @return array{type: string, id: string, data: array<string, mixed>}|null */
    private function reportedEntity(Request $request): ?array
    {
        $entity = match ($this->entity_type) {
            'post' => $this->relationLoaded('reportedPost') ? $this->reportedPost : null,
            'campaign' => $this->relationLoaded('reportedCampaign') ? $this->reportedCampaign : null,
            'user' => $this->relationLoaded('reportedUser') ? $this->reportedUser : null,
            'organization' => $this->relationLoaded('reportedOrganization') ? $this->reportedOrganization : null,
            default => null,
        };

        if ($entity === null) {
            return null;
        }

        $data = match ($this->entity_type) {
            'post' => PostResource::make($entity)->resolve($request),
            'campaign' => CampaignResource::make($entity)->resolve($request),
            'user' => UserResource::make($entity)->resolve($request),
            'organization' => OrganizationResource::make($entity)->resolve($request),
            default => [],
        };

        return [
            'type' => (string) $this->entity_type,
            'id' => (string) $entity->getKey(),
            'data' => $data,
        ];
    }
}
