<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use App\Models\User;
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
            'reporter' => $this->reporterSummary(),
            'reportedTarget' => $this->reportedTargetSummary(),
            'organizationName' => $this->organization?->name,
            'reporterName' => $this->reporter?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->userSummary($this->assignee)),
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

    private function reporterSummary(): ?array
    {
        if (! $this->relationLoaded('reporter')) {
            return null;
        }

        return $this->userSummary($this->reporter);
    }

    private function reportedTargetSummary(): ?array
    {
        return match ($this->entity_type) {
            'post' => $this->reportedPostTarget(),
            'campaign' => $this->relationLoaded('reportedCampaign')
                ? $this->organizationSummary($this->reportedCampaign?->organization)
                : null,
            'user' => $this->relationLoaded('reportedUser')
                ? $this->targetUserSummary($this->reportedUser)
                : null,
            'organization' => $this->relationLoaded('reportedOrganization')
                ? $this->organizationSummary($this->reportedOrganization)
                : null,
            default => null,
        };
    }

    private function reportedPostTarget(): ?array
    {
        if (! $this->relationLoaded('reportedPost') || $this->reportedPost === null) {
            return null;
        }

        if ($this->reportedPost->organization !== null) {
            return $this->organizationSummary($this->reportedPost->organization);
        }

        return $this->targetUserSummary($this->reportedPost->author);
    }

    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'email' => $user->email,
        ];
    }

    private function targetUserSummary(?User $user): ?array
    {
        $summary = $this->userSummary($user);

        return $summary === null ? null : [...$summary, 'type' => 'user'];
    }

    private function organizationSummary(?Organization $organization): ?array
    {
        if ($organization === null) {
            return null;
        }

        return [
            'id' => (string) $organization->id,
            'name' => (string) $organization->name,
            'email' => $organization->email,
            'type' => 'organization',
        ];
    }
}
