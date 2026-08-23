<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'campaignTitle' => $this->campaign_title,
            'applicantStatus' => $this->applicant_status,
            'appliedAt' => $this->applied_at?->toIso8601String(),
        ];
    }
}
