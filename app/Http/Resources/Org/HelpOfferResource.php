<?php

declare(strict_types=1);

namespace App\Http\Resources\Org;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HelpOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $organizationId = (string) $request->user()?->organization_id;
        $ownsRequest = $organizationId !== '' && (string) $this->post?->organization_id === $organizationId;
        $status = $this->status?->value ?? (string) $this->status;
        $canSeeContact = $ownsRequest && $this->accepted_at !== null;

        return [
            'id' => (string) $this->id,
            'postId' => (string) $this->post_id,
            'request' => $this->whenLoaded('post', fn () => ['id' => (string) $this->post?->id, 'title' => $this->post?->title, 'helpStatus' => $this->post?->help_status?->value ?? $this->post?->help_status]),
            'helper' => ['id' => (string) $this->helper_user_id, 'name' => $this->helper?->name],
            'type' => $this->type,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'description' => $this->description,
            'status' => $status,
            'contactMethod' => $canSeeContact ? $this->contact_method : null,
            'phone' => $canSeeContact ? $this->phone : null,
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
            'contactedAt' => $this->contacted_at?->toIso8601String(),
            'agreedAt' => $this->agreed_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'can' => [
                'accept' => $ownsRequest && $status === 'pending',
                'reject' => $ownsRequest && $status === 'pending',
                'contact' => $ownsRequest && $status === 'accepted',
                'confirmReceived' => $ownsRequest && $status === 'agreed',
            ],
        ];
    }
}
