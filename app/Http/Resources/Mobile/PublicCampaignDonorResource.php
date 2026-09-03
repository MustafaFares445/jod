<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCampaignDonorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isAnonymous = (bool) $this->is_anonymous;
        $creator = $this->relationLoaded('creator') ? $this->creator : null;

        return [
            'id' => (string) $this->id,
            'name' => $isAnonymous ? 'مجهول' : (string) ($creator?->name ?? $this->name ?? 'متبرع'),
            'avatarUrl' => $isAnonymous ? null : ($creator?->relationLoaded('avatarMedia') ? $creator->avatarMedia?->publicUrl() : null),
            'amount' => (float) $this->amount_or_type,
            'donatedAt' => ($this->completed_at ?? $this->donated_at ?? $this->created_at)?->toIso8601String(),
            'isAnonymous' => $isAnonymous,
        ];
    }
}
