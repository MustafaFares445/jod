<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = (string) $request->user()?->id;
        $isOwner = $viewerId !== '' && $viewerId === (string) $this->creator_id;
        $images = $this->relationLoaded('imageMedia') ? $this->imageMedia : $this->resource->imageMedia()->get();
        $category = $this->relationLoaded('category') ? $this->category : $this->resource->category()->first();
        $creator = $this->relationLoaded('creator') ? $this->creator : $this->resource->creator()->first();

        return [
            'id' => (string) $this->id,
            'ownerType' => 'personal',
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'categoryId' => $this->category_id,
            'category' => $category ? ['id' => (string) $category->id, 'name' => (string) $category->name] : null,
            'audience' => $this->audience ?? 'general',
            'status' => (string) $this->status,
            'location' => $this->location,
            'goalAmount' => (float) $this->goal_amount,
            'raisedAmount' => (float) $this->raised_amount,
            'beneficiariesCount' => (int) $this->beneficiaries_count,
            'donorsCount' => (int) $this->donors_count,
            'images' => $images->map(static fn (Media $image): string => $image->publicUrl())->values()->all(),
            'creator' => $creator ? [
                'id' => (string) $creator->id,
                'name' => (string) $creator->name,
                'email' => $creator->email,
                'phone' => $creator->phone,
                'city' => $creator->city,
                'avatarUrl' => $creator->relationLoaded('avatarMedia') ? $creator->avatarMedia?->publicUrl() : null,
            ] : null,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'rejectionReason' => $this->rejection_reason,
            'suspensionReason' => $this->suspension_reason,
            'closedAt' => $this->closed_at?->toIso8601String(),
            'closedReason' => $this->closed_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'can' => [
                'edit' => $isOwner && in_array((string) $this->status, ['pending', 'rejected'], true),
                'close' => $isOwner && (string) $this->status === 'active',
                'manageDonations' => $isOwner && in_array((string) $this->status, ['active', 'closed', 'suspended'], true),
            ],
        ];
    }
}
