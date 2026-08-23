<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $post = MobileHomePostResource::make($this->post)->resolve($request);
        $post['saved'] = true;
        $post['isSaved'] = true;
        $post['savedAt'] = $this->created_at?->toIso8601String();

        return $post;
    }
}
