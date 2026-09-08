<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'campaignId' => $this->campaign_id ? (string) $this->campaign_id : null,
            'postId' => $this->campaign_id === null && $this->request_type === 'volunteer' && $this->campaign_ref ? (string) $this->campaign_ref : null,
            'campaignTitle' => $this->campaign_title,
            'organizationName' => $this->campaign?->organization?->name ?? $this->organization?->name,
            'status' => (string) $this->applicant_status,
            'phone' => $this->phone,
            'city' => $this->city,
            'withdrawalReason' => $this->withdrawal_reason,
            'rejectionReason' => $this->rejection_reason,
            'submittedAt' => $this->applied_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
