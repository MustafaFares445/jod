<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organizationName = $this->campaign?->organization?->name;
        $amount = (float) $this->amount_or_type;
        $donatedAt = $this->donated_at?->toIso8601String();

        return [
            'id' => (string) $this->id,
            'campaignId' => (string) $this->campaign_id,
            'campaignTitle' => $this->campaign_title,
            'organizationName' => $organizationName,
            'amount' => $amount,
            'paymentMethod' => $this->payment_method,
            'phone' => $this->phone,
            'city' => $this->city,
            'source' => $this->source,
            'donatedAt' => $donatedAt,
            'createdAt' => $this->created_at?->toIso8601String(),

            // Mobile My Donations screen contract.
            'organization' => $organizationName,
            'donatedAmount' => $amount,
            'targetAmount' => (float) ($this->campaign?->goal_amount ?? 0),
            'date' => $donatedAt,
            'status' => $this->campaign?->status,
            'flow' => $this->flow($request),
        ];
    }

    private function flow(Request $request): string
    {
        $requestedFlow = $request->query('flow');
        if (in_array($requestedFlow, ['contributed', 'received'], true)) {
            return $requestedFlow;
        }

        return $request->user()?->id === $this->created_by
            ? 'contributed'
            : 'received';
    }
}
