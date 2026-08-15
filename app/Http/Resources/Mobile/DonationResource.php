<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     campaignId: string,
     *     campaignTitle: string,
     *     organizationName: string|null,
     *     amount: float,
     *     paymentMethod: string|null,
     *     phone: string|null,
     *     city: string|null,
     *     source: string|null,
     *     donatedAt: string|null,
     *     createdAt: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'campaignId' => (string) $this->campaign_id,
            'campaignTitle' => $this->campaign_title,
            'organizationName' => $this->campaign?->organization?->name,
            'amount' => (float) $this->amount_or_type,
            'paymentMethod' => $this->payment_method,
            'phone' => $this->phone,
            'city' => $this->city,
            'source' => $this->source,
            'donatedAt' => $this->donated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
