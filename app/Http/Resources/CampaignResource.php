<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->relationLoaded('imageMedia') ? $this->imageMedia : $this->resource->imageMedia()->get();
        $category = $this->relationLoaded('category') ? $this->category : $this->resource->category()->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'categoryId' => $this->category_id,
            'category' => $category?->name,
            'audience' => $this->audience ?? 'general',
            'status' => $this->status,
            'organizationName' => $this->organization?->name,
            'managerName' => $this->creator?->name,
            'location' => $this->location,
            'images' => $images->map(static fn (Media $image): string => $image->publicUrl())->values()->all(),
            'media' => $images->map(fn (Media $image): array => MediaResource::make($image)->resolve($request))->values()->all(),
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
            'closedReason' => $this->closed_reason,
            'reviewedBy' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'rejectionReason' => $this->rejection_reason,
        ];
    }
}
