<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'campaignTitle' => $this->campaign_title,
            'amountOrType' => $this->amount_or_type,
            'donatedAt' => $this->donated_at?->toIso8601String(),
            'city' => $this->city,
            'source' => $this->source,
            'paymentMethod' => $this->payment_method,
            'campaignRef' => $this->campaign_ref,
            'assignedTo' => $this->assigned_to,
            'internalNotes' => $this->internal_notes,
        ];
    }
}
