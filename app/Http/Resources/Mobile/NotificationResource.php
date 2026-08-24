<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $action = null;
        if (filled($this->reference_label) || filled($this->reference_path)) {
            $action = array_filter([
                'label' => $this->reference_label,
                'route' => $this->reference_path,
            ], static fn (mixed $value): bool => filled($value));
        }

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->mobileType(),
            'category' => $this->category,
            'eventType' => $this->event_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'isRead' => $this->status === 'read',
            'actionLabel' => $this->reference_label,
            'action' => $action,
            'referenceLabel' => $this->reference_label,
            'referencePath' => $this->reference_path,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    private function mobileType(): string
    {
        return match ($this->category) {
            'campaign', 'donation' => 'campaign',
            'applicant' => 'volunteer',
            'post' => $this->postType(),
            default => 'system',
        };
    }

    private function postType(): string
    {
        $path = Str::lower((string) $this->reference_path);
        $label = Str::lower((string) $this->reference_label);

        return Str::contains($path.' '.$label, ['saved', 'محفوظ'])
            ? 'saved'
            : 'system';
    }
}
