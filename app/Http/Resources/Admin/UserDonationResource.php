<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDonationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'campaignId' => $this->campaign_id !== null ? (string) $this->campaign_id : null,
            'campaignTitle' => $this->campaign_title ?? $this->campaign?->title,
            'organizationId' => $this->organization_id !== null ? (string) $this->organization_id : null,
            'organizationName' => $this->organization?->name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'amountOrType' => $this->amount_or_type,
            'amount' => is_numeric($this->amount_or_type) ? (float) $this->amount_or_type : null,
            'paymentMethod' => $this->payment_method,
            'city' => $this->city,
            'source' => $this->source,
            'campaignRef' => $this->campaign_ref,
            'internalNotes' => $this->internal_notes,
            'donatedAt' => $this->donated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
