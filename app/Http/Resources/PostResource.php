<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->resource->images()->get();
        $videos = $this->relationLoaded('videos') ? $this->videos : $this->resource->videos()->get();
        $media = $images->concat($videos)->values();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'description' => $this->content,
            'body' => $this->content,
            'type' => $this->type,
            'audience' => $this->audience ?? 'general',
            'status' => $this->status,
            'helpStatus' => $this->help_status?->value ?? $this->help_status,
            'urgency' => $this->urgency?->value ?? $this->urgency ?? 'normal',
            'urgencyReason' => $this->urgency_reason,
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'fulfilledAt' => $this->fulfilled_at?->toIso8601String(),
            'urgencyReviewedAt' => $this->urgency_reviewed_at?->toIso8601String(),
            'urgencyReviewedBy' => $this->whenLoaded('urgencyReviewedBy', fn () => $this->userSummary($this->urgencyReviewedBy)),
            'organizationName' => $this->organization?->name,
            'authorName' => $this->whenLoaded('author', fn () => $this->author?->name),
            'author' => $this->whenLoaded('author', fn () => $this->userSummary($this->author)),
            'publisher' => $this->publisherSummary(),
            'categoryId' => $this->category_id ? (string) $this->category_id : null,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => (string) $this->category->id, 'name' => (string) $this->category->name] : null),
            'updatedBy' => $this->whenLoaded('updatedBy', fn () => $this->userSummary($this->updatedBy)),
            'updatedByName' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy?->name),
            'location' => $this->location,
            'campaignTitle' => $this->whenLoaded('campaign', fn () => $this->campaign?->title, $this->campaign?->title),
            'images' => $images->map(static fn (Media $image): string => $image->publicUrl())->values()->all(),
            'videos' => $videos->map(static fn (Media $video): string => $video->publicUrl())->values()->all(),
            'media' => $media->map(fn (Media $item): array => MediaResource::make($item)->resolve($request))->values()->all(),
            'submittedAt' => $this->submitted_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'reviewedBy' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'reviewedByUser' => $this->whenLoaded('reviewedBy', fn () => $this->userSummary($this->reviewedBy)),
            'blockedAt' => $this->blocked_at?->toIso8601String(),
            'blockedBy' => $this->whenLoaded('blockedBy', fn () => $this->userSummary($this->blockedBy)),
            'blockReason' => $this->block_reason,
            'viewsCount' => (int) $this->views_count,
            'reactionsCount' => (int) $this->reactions_count,
            'applicationsCount' => (int) $this->applications_count,
        ];
    }

    private function publisherSummary(): array
    {
        if ($this->relationLoaded('organization') && $this->organization !== null) return ['id' => (string) $this->organization->id, 'name' => (string) $this->organization->name, 'type' => 'organization'];
        $author = $this->relationLoaded('author') ? $this->author : null;
        return ['id' => $author?->id ? (string) $author->id : (string) ($this->author_id ?? ''), 'name' => (string) ($author?->name ?? $this->author_name ?? 'JOD'), 'type' => $author?->user_type === 'admin' ? 'admin' : 'user'];
    }

    private function userSummary(?User $user): ?array
    {
        if ($user === null) return null;
        return ['id' => (string) $user->id, 'name' => (string) $user->name, 'email' => $user->email];
    }
}
