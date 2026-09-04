<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PostData extends Data
{
    /** @param list<string> $requiredCapabilityIds */
    public function __construct(
        public string $title,
        public string $summary,
        public string $type,
        public string $location,
        public ?string $campaignTitle = null,
        public string $status = 'published',
        public string $audience = 'general',
        public ?string $categoryId = null,
        public string $urgency = 'normal',
        public ?string $urgencyReason = null,
        public ?string $expiresAt = null,
        public array $requiredCapabilityIds = [],
    ) {}
}
