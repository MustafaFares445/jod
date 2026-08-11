<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $post = PostResource::make($this->post)->resolve($request);
        $post['savedAt'] = $this->created_at?->toIso8601String();

        return $post;
    }
}
