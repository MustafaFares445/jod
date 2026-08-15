<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     body: string,
     *     category: string,
     *     priority: string,
     *     status: string,
     *     isRead: bool,
     *     referenceLabel: string|null,
     *     referencePath: string|null,
     *     sentAt: string|null,
     *     readAt: string|null,
     *     createdAt: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'isRead' => $this->status === 'read',
            'referenceLabel' => $this->reference_label,
            'referencePath' => $this->reference_path,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
