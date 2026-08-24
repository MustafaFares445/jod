<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HelpOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = (string) $request->user()?->id;
        $isHelper = $userId !== '' && $userId === (string) $this->helper_user_id;
        $isOwner = $userId !== '' && $userId === (string) $this->post_owner_id;
        $ownerCanSeeContact = $isOwner && $this->accepted_at !== null;
        $canSeeContact = $isHelper || $ownerCanSeeContact;

        return [
            'id' => (string) $this->id,
            'postId' => (string) $this->post_id,
            'post' => $this->whenLoaded('post', fn () => [
                'id' => (string) $this->post?->id,
                'title' => $this->post?->title,
                'helpStatus' => $this->post?->help_status?->value ?? $this->post?->help_status,
            ]),
            'helper' => [
                'id' => (string) $this->helper_user_id,
                'name' => $this->helper?->name,
            ],
            'type' => $this->type,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'description' => $this->description,
            'status' => $this->status?->value ?? (string) $this->status,
            'contactMethod' => $canSeeContact ? $this->contact_method : null,
            'phone' => $canSeeContact ? $this->phone : null,
            'cancelReason' => ($isHelper || $isOwner) ? $this->cancel_reason : null,
            'rejectionReason' => ($isHelper || $isOwner) ? $this->rejection_reason : null,
            'createdAt' => $this->created_at?->toIso8601String(),
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
            'contactedAt' => $this->contacted_at?->toIso8601String(),
            'agreedAt' => $this->agreed_at?->toIso8601String(),
            'helperConfirmedAt' => $this->helper_confirmed_at?->toIso8601String(),
            'receiverConfirmedAt' => $this->receiver_confirmed_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'can' => [
                'accept' => $isOwner && ($this->status?->value ?? $this->status) === 'pending',
                'reject' => $isOwner && ($this->status?->value ?? $this->status) === 'pending',
                'confirmProvided' => $isHelper && ($this->status?->value ?? $this->status) === 'agreed',
                'confirmReceived' => $isOwner && ($this->status?->value ?? $this->status) === 'agreed',
            ],
        ];
    }
}
