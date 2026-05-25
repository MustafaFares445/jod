<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'mailbox' => $this->mailbox,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'recipientScope' => $this->recipient_scope,
            'recipientLabel' => $this->recipient_label,
            'priority' => $this->priority,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
            'referenceLabel' => $this->reference_label,
            'referencePath' => $this->reference_path,
            'createdBy' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
        ];
    }
}
