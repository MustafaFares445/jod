<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HelpMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $post = $this->whenLoaded('post');
        $helper = $this->whenLoaded('helper');
        $owner = $this->whenLoaded('postOwner');
        $preference = $helper?->relationLoaded('preference') ? $helper->preference : null;
        $interest = $helper?->relationLoaded('categoryInterests') && $post?->category_id
            ? $helper->categoryInterests->firstWhere('category_id', $post->category_id)
            : null;

        return [
            'id' => (string) $this->id,
            'type' => (string) $this->type,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'contactMethod' => $this->contact_method,
            'phone' => $this->phone,
            'cancelReason' => $this->cancel_reason,
            'rejectionReason' => $this->rejection_reason,
            'request' => $post ? [
                'id' => (string) $post->id,
                'title' => (string) $post->title,
                'status' => (string) $post->status,
                'fulfillmentStatus' => $post->help_status?->value ?? $post->help_status,
                'urgency' => $post->urgency?->value ?? $post->urgency ?? 'normal',
                'location' => $post->location,
                'category' => $post->relationLoaded('category') && $post->category ? [
                    'id' => (string) $post->category->id,
                    'name' => (string) $post->category->name,
                ] : null,
            ] : null,
            'helper' => $helper ? [
                'id' => (string) $helper->id,
                'name' => (string) $helper->name,
                'email' => $helper->email,
                'phone' => $helper->phone,
                'preferredCity' => $preference?->preferred_city,
                'availabilityStatus' => $preference?->availability_status?->value ?? $preference?->availability_status,
                'capabilities' => $helper->relationLoaded('capabilities')
                    ? $helper->capabilities->map(fn ($capability) => [
                        'id' => (string) $capability->id,
                        'name' => (string) $capability->name,
                        'slug' => (string) $capability->slug,
                    ])->values()
                    : [],
            ] : null,
            'requestOwner' => $owner ? [
                'id' => (string) $owner->id,
                'name' => (string) $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
            ] : null,
            'signals' => [
                'preferredCity' => $preference?->preferred_city,
                'requestLocation' => $post?->location,
                'explicitCategoryWeight' => $interest ? (float) $interest->explicit_weight : 0,
                'behavioralCategoryWeight' => $interest ? (float) $interest->behavioral_weight : 0,
            ],
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
            'contactedAt' => $this->contacted_at?->toIso8601String(),
            'agreedAt' => $this->agreed_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'ageHours' => $this->created_at ? $this->created_at->diffInHours(now()) : 0,
            'isStale' => in_array($this->status?->value ?? $this->status, ['pending', 'accepted', 'contacting'], true)
                && $this->updated_at?->lessThanOrEqualTo(now()->subHours(24)),
        ];
    }
}
