<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $organizationName = $this->campaign?->organization?->name;
        $amount = (float) $this->amount_or_type;

        return [
            'id' => (string) $this->id,
            'campaignId' => (string) $this->campaign_id,
            'campaignTitle' => $this->campaign_title,
            'organizationName' => $organizationName,
            'amount' => $amount,
            'status' => $this->status?->value ?? (string) $this->status,
            'contactMethod' => $this->contact_method,
            'paymentMethod' => $this->payment_method,
            'phone' => $this->phone,
            'city' => $this->city,
            'notes' => $this->notes,
            'cancelReason' => $this->cancel_reason,
            'source' => $this->source,
            'createdAt' => $this->created_at?->toIso8601String(),
            'contactedAt' => $this->contacted_at?->toIso8601String(),
            'agreedAt' => $this->agreed_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),

            // Backward-compatible fields for the existing My Donations screen.
            'organization' => $organizationName,
            'donatedAmount' => $amount,
            'targetAmount' => (float) ($this->campaign?->goal_amount ?? 0),
            'date' => ($this->completed_at ?? $this->created_at)?->toIso8601String(),
            'flow' => $this->flow($request),
        ];
    }

    private function flow(Request $request): string
    {
        $requestedFlow = $request->query('flow');
        if (in_array($requestedFlow, ['contributed', 'received'], true)) {
            return $requestedFlow;
        }

        return (string) $request->user()?->id === (string) $this->created_by ? 'contributed' : 'received';
    }
}
