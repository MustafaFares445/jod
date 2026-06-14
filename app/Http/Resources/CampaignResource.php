<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'category' => $this->category,
            'status' => $this->status,
            'organizationName' => $this->organization?->name,
            'managerName' => $this->creator?->name,
            'location' => $this->location,
            'goalAmount' => (float) $this->goal_amount,
            'raisedAmount' => (float) $this->raised_amount,
            'beneficiariesCount' => (int) $this->beneficiaries_count,
            'donorsCount' => (int) $this->donors_count,
            'applicantsCount' => (int) $this->applicants_count,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'submittedAt' => $this->submitted_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
            'closedReason' => $this->close_reason,
            'reviewedBy' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'rejectionReason' => $this->rejection_reason,
        ];
    }
}
