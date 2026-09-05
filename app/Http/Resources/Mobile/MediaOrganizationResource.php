<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaOrganizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'verified' => $this->verification_status === 'verified',
            'image' => $this->logoMedia?->publicUrl(),
            'logo' => $this->whenLoaded('logoMedia', function () use ($request): ?array {
                if ($this->logoMedia === null) {
                    return null;
                }

                return MediaResource::make($this->logoMedia)->resolve($request);
            }),
        ];
    }
}
