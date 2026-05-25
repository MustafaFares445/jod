<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PostData extends Data
{
    public function __construct(
        public string $title,
        public string $summary,
        public string $type,
        public string $status,
        public string $authorName,
        public string $location,
        public ?string $campaignTitle = null,
    ) {}
}
