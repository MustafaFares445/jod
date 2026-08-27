<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaEngagementStateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['mediaId' => (string) $this->resource['mediaId']];

        if (array_key_exists('isLiked', $this->resource)) {
            $data['isLiked'] = (bool) $this->resource['isLiked'];
            $data['likesCount'] = (int) $this->resource['likesCount'];
        }

        if (array_key_exists('isSaved', $this->resource)) {
            $data['isSaved'] = (bool) $this->resource['isSaved'];
            $data['savesCount'] = (int) $this->resource['savesCount'];
        }

        return $data;
    }
}
