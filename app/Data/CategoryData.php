<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class CategoryData extends Data
{
    public function __construct(
        #[Max(255)]
        public ?string $name = null,
        #[In('post', 'campaign')]
        public ?string $target = null,
        #[Max(1000)]
        public ?string $description = null,
        #[In('active', 'inactive')]
        public ?string $status = null,
        public ?int $usageCount = 0,
    ) {}

    public function onlyModelAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'target' => $this->target,
            'description' => $this->description,
            'status' => $this->status,
            'usage_count' => $this->usageCount,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
