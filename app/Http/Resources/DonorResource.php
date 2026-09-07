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
            'amount' => is_numeric($this->amount_or_type) ? (float) $this->amount_or_type : $this->amount_or_type,
            'status' => $this->status?->value ?? (string) $this->status,
            'contactMethod' => $this->contact_method,
            'paymentMethod' => $this->payment_method,
            'notes' => $this->notes,
            'isAnonymous' => (bool) $this->is_anonymous,
            'cancelReason' => $this->cancel_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'contactedAt' => $this->contacted_at?->toIso8601String(),
            'agreedAt' => $this->agreed_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
