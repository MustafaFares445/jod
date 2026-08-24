<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaUploadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $received = collect($this->received_chunks ?? [])
            ->map(static fn ($chunk): int => (int) $chunk)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $all = range(0, max(0, ((int) $this->total_chunks) - 1));
        $missing = array_values(array_diff($all, $received));
        $totalSize = (int) $this->total_size;
        $uploadedBytes = min((int) $this->uploaded_bytes, $totalSize);
        $isExpired = $this->expires_at !== null && $this->expires_at->isPast() && $this->status !== 'completed';

        return [
            'id' => (string) $this->id,
            'organizationId' => (string) $this->organization_id,
            'replaceVideoId' => $this->replace_media_id,
            'videoId' => $this->media_id,
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'totalSize' => $totalSize,
            'chunkSize' => (int) $this->chunk_size,
            'totalChunks' => (int) $this->total_chunks,
            'receivedChunks' => $received,
            'missingChunks' => $missing,
            'nextChunk' => $missing[0] ?? null,
            'uploadedBytes' => $uploadedBytes,
            'progressPercent' => $totalSize > 0 ? round(($uploadedBytes / $totalSize) * 100, 2) : 0,
            'status' => $this->status,
            'isExpired' => $isExpired,
            'canPause' => ! $isExpired && in_array($this->status, ['initiated', 'uploading'], true),
            'canResume' => ! $isExpired && $this->status === 'paused',
            'canComplete' => ! $isExpired && count($missing) === 0 && in_array($this->status, ['initiated', 'uploading'], true),
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
