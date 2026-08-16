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
            'campaignTitle' => $this->campaign_title,
            'organizationName' => $this->campaign?->organization?->name ?? $this->organization?->name,
            'status' => $this->applicant_status,
            'phone' => $this->phone,
            'city' => $this->city,
            'submittedAt' => $this->applied_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
