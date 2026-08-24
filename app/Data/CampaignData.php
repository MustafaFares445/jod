<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class CampaignData extends Data
{
    public function __construct(
        public string $title,
        public string $summary,
        public string $location,
        public float $goalAmount,
        public int $beneficiariesCount,
        public string $startDate,
        public string $endDate,
        public ?string $category = null,
        public ?string $categoryId = null,
        public ?string $status = 'draft',
    ) {}
}
