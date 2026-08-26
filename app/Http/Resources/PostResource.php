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
            'organizationName' => $this->organization?->name,
            'authorName' => $this->whenLoaded('author', fn () => $this->author?->name),
            'author' => $this->whenLoaded('author', fn () => $this->userSummary($this->author)),
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
            'approvedAt' => $this->approved_at?->toIso8601String(),
            'approvedBy' => $this->whenLoaded('approvedBy', fn () => $this->userSummary($this->approvedBy)),
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'rejectedBy' => $this->whenLoaded('rejectedBy', fn () => $this->userSummary($this->rejectedBy)),
            'rejectionReason' => $this->rejection_reason,
            'viewsCount' => (int) $this->views_count,
            'reactionsCount' => (int) $this->reactions_count,
            'applicationsCount' => (int) $this->applications_count,
        ];
    }

    private function userSummary(?User $user): ?array
    {
        if ($user === null) return null;
        return ['id' => (string) $user->id, 'name' => (string) $user->name, 'email' => $user->email];
    }
}
