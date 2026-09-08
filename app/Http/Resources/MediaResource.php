<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MediaModel;
use App\Http\Resources\Mobile\MediaOrganizationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $modelType = $this->model_type instanceof MediaModel
            ? $this->model_type->value
            : (string) $this->model_type;
        $post = $modelType === MediaModel::POST->value && $this->relationLoaded('post')
            ? $this->post
            : null;
        $postOrganization = $post?->relationLoaded('organization') === true ? $post->organization : null;
        $postAuthor = $post?->relationLoaded('author') === true ? $post->author : null;

        $data = [
            'id' => $this->id,
            'model' => $modelType,
            'modelId' => $this->model_id,
            'prop' => $this->prop,
            'url' => $this->publicUrl(),
            'streamUrl' => $this->when(
                $this->prop === 'videos',
                fn (): string => route('mobile.discovery.media.stream', ['video' => $this->id]),
            ),
            'previewUrl' => $this->when(
                $this->prop === 'videos',
                fn (): ?string => $this->preview_status === 'ready' && filled($this->preview_path)
                    ? route('mobile.discovery.media.preview', [
                        'video' => $this->id,
                        'v' => substr(sha1((string) $this->preview_path), 0, 12),
                    ])
                    : null,
            ),
            'previewStatus' => $this->when(
                $this->prop === 'videos',
                fn (): ?string => $this->preview_status,
            ),
            'previewMimeType' => $this->when(
                $this->prop === 'videos',
                fn (): ?string => $this->preview_mime_type,
            ),
            'previewSize' => $this->when(
                $this->prop === 'videos',
                fn (): ?int => $this->preview_size !== null ? (int) $this->preview_size : null,
            ),
            'originalName' => $this->original_name,
            'description' => $this->description,
            'mimeType' => $this->mime_type,
            'size' => (int) $this->size,
            'position' => (int) $this->position,
            'likesCount' => (int) ($this->reactions_count ?? 0),
            'savesCount' => (int) ($this->saves_count ?? 0),
            'isLiked' => $this->relationLoaded('likes') && $this->likes->isNotEmpty(),
            'isSaved' => $this->relationLoaded('saves') && $this->saves->isNotEmpty(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'organization' => $this->whenLoaded('organization', function () use ($request): ?array {
                if ($this->organization === null) {
                    return null;
                }

                return MediaOrganizationResource::make($this->organization)->resolve($request);
            }),
        ];

        if ($post !== null) {
            $publisherName = $postOrganization?->name ?? $postAuthor?->name ?? 'JOD';
            $publisherEmail = $postOrganization?->email ?? $postAuthor?->email;

            $data['post'] = [
                'id' => (string) $post->id,
                'title' => $post->title,
                'summary' => $post->summary,
                'content' => $post->content,
                'location' => $post->location,
                'audience' => $post->audience ?? 'general',
                'publishedAt' => ($post->published_at ?? $post->created_at)?->toIso8601String(),
            ];
            $data['publisher'] = [
                'id' => (string) ($postOrganization?->id ?? $postAuthor?->id ?? $post->author_id ?? 'jod'),
                'publisherType' => $postOrganization !== null ? 'organization' : 'user',
                'name' => (string) $publisherName,
                'username' => filled($publisherEmail)
                    ? Str::before((string) $publisherEmail, '@')
                    : (Str::slug((string) $publisherName, '.') ?: 'jod'),
                'verified' => $postOrganization !== null
                    ? $postOrganization->verification_status === 'verified'
                    : $postAuthor?->email_verified_at !== null,
                'avatarUrl' => $postOrganization?->logoMedia?->publicUrl() ?? $postAuthor?->avatarMedia?->publicUrl(),
            ];
        }

        return $data;
    }
}
