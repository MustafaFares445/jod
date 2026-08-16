<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngagementStateResource extends JsonResource
{
    /**
     * @return array{postId: string, isLiked?: bool, likesCount?: int, isSaved?: bool, savesCount?: int, sharesCount?: int}
     */
    public function toArray(Request $request): array
    {
        $data = [
            'postId' => (string) $this->resource['postId'],
        ];

        if (array_key_exists('isLiked', $this->resource)) {
            $data['isLiked'] = (bool) $this->resource['isLiked'];
            $data['likesCount'] = (int) $this->resource['likesCount'];
        }

        if (array_key_exists('isSaved', $this->resource)) {
            $data['isSaved'] = (bool) $this->resource['isSaved'];
            $data['savesCount'] = (int) $this->resource['savesCount'];
        }

        if (array_key_exists('sharesCount', $this->resource)) {
            $data['sharesCount'] = (int) $this->resource['sharesCount'];
        }

        return $data;
    }
}
