<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $targetType = $this->campaign_id !== null
            ? 'campaign'
            : (filled($this->campaign_ref) ? 'post' : 'manual');
        $targetId = $this->campaign_id !== null
            ? (string) $this->campaign_id
            : (filled($this->campaign_ref) ? (string) $this->campaign_ref : null);
        $hasDonationWorkflow = $this->campaign_id !== null && $this->created_by !== null;

        return [
            'id' => (string) $this->id,
            'campaignId' => $this->campaign_id !== null ? (string) $this->campaign_id : null,
            'campaignTitle' => $this->campaign_title,
            'targetType' => $targetType,
            'targetId' => $targetId,
            'source' => $this->source,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'amount' => $this->confirmed_amount !== null
                ? (float) $this->confirmed_amount
                : (is_numeric($this->amount_or_type) ? (float) $this->amount_or_type : $this->amount_or_type),
            'requestedAmount' => is_numeric($this->amount_or_type) ? (float) $this->amount_or_type : null,
            'confirmedAmount' => $this->confirmed_amount !== null ? (float) $this->confirmed_amount : null,
            'status' => $this->status?->value ?? (string) $this->status,
            'contactMethod' => $this->contact_method,
            'paymentMethod' => $this->payment_method,
            'notes' => $this->notes,
            'isAnonymous' => (bool) $this->is_anonymous,
            'can' => [
                'accept' => $hasDonationWorkflow && ($this->status?->value ?? (string) $this->status) === 'pending',
                'contact' => $hasDonationWorkflow && ($this->status?->value ?? (string) $this->status) === 'accepted',
                'agree' => $hasDonationWorkflow && ($this->status?->value ?? (string) $this->status) === 'contacting',
                'complete' => $hasDonationWorkflow && ($this->status?->value ?? (string) $this->status) === 'agreed',
                'cancel' => $hasDonationWorkflow && in_array(($this->status?->value ?? (string) $this->status), ['pending', 'accepted', 'contacting', 'agreed'], true),
            ],
            'cancelReason' => $this->cancel_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
            'contactedAt' => $this->contacted_at?->toIso8601String(),
            'agreedAt' => $this->agreed_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
