<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'type' => $this->type,
            'status' => $this->status,
            'organizationName' => $this->organization?->name,
            'authorName' => $this->author_name,
            'location' => $this->location,
            'campaignTitle' => $this->whenLoaded('campaign', fn () => $this->campaign?->title, $this->campaign?->title),
            'submittedAt' => $this->submitted_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'reviewedBy' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'rejectionReason' => $this->rejection_reason,
            'viewsCount' => (int) $this->views_count,
            'reactionsCount' => (int) $this->reactions_count,
            'applicationsCount' => (int) $this->applications_count,
        ];
    }
}
