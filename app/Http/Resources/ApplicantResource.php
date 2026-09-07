<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStandalonePost = $this->campaign_id === null
            && $this->request_type === 'volunteer'
            && filled($this->campaign_ref);

        $status = (string) $this->applicant_status;

        return [
            'id' => (string) $this->id,
            'campaignId' => $this->campaign_id !== null ? (string) $this->campaign_id : null,
            'postId' => $isStandalonePost ? (string) $this->campaign_ref : null,
            'targetType' => $isStandalonePost ? 'post' : 'campaign',
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'campaignTitle' => $this->campaign_title,
            'applicantStatus' => $status,
            'requestType' => $this->request_type,
            'source' => $this->source,
            'can' => [
                'accept' => in_array($status, ['pending', 'under_review'], true),
                'contact' => in_array($status, ['accepted', 'approved'], true),
                'complete' => $status === 'contacting',
                'reject' => in_array($status, ['pending', 'under_review', 'accepted', 'approved'], true),
            ],
            'appliedAt' => $this->applied_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
