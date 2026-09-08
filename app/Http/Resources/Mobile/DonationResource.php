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
        $campaignOwnerName = $organizationName ?? $this->campaign?->group?->name ?? $this->campaign?->creator?->name;
        $viewerId = (string) $request->user()?->id;
        $isPersonalOwner = $viewerId !== '' && $viewerId === (string) $this->campaign?->creator_id
            && blank($this->campaign?->organization_id) && blank($this->campaign?->group_id);
        $requestedAmount = (float) $this->amount_or_type;
        $confirmedAmount = $this->confirmed_amount !== null ? (float) $this->confirmed_amount : null;
        $amount = $confirmedAmount ?? $requestedAmount;

        return [
            'id' => (string) $this->id,
            'campaignId' => (string) $this->campaign_id,
            'campaignTitle' => $this->campaign_title,
            'organizationName' => $organizationName,
            'campaignOwnerName' => $campaignOwnerName,
            'donorName' => $isPersonalOwner ? $this->name : null,
            'donorEmail' => $isPersonalOwner ? $this->email : null,
            'amount' => $amount,
            'requestedAmount' => $requestedAmount,
            'confirmedAmount' => $confirmedAmount,
            'status' => $this->status?->value ?? (string) $this->status,
            'contactMethod' => $this->contact_method,
            'paymentMethod' => $this->payment_method,
            'phone' => $this->phone,
            'city' => $this->city,
            'notes' => $this->notes,
            'isAnonymous' => (bool) $this->is_anonymous,
            'cancelReason' => $this->cancel_reason,
            'source' => $this->source,
            'createdAt' => $this->created_at?->toIso8601String(),
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
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
            'can' => [
                'accept' => $isPersonalOwner && ($this->status?->value ?? $this->status) === 'pending',
                'contact' => $isPersonalOwner && ($this->status?->value ?? $this->status) === 'accepted',
                'agree' => $isPersonalOwner && ($this->status?->value ?? $this->status) === 'contacting',
                'complete' => $isPersonalOwner && ($this->status?->value ?? $this->status) === 'agreed',
                'cancel' => $isPersonalOwner && in_array(($this->status?->value ?? $this->status), ['pending','accepted','contacting','agreed'], true),
            ],
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
