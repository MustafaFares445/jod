<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Max;

class BadgeData extends Data
{
    public function __construct(
        #[Max(255)]
        public ?string $name = null,
        #[Max(1000)]
        public ?string $description = null,
        #[Max(1000)]
        public ?string $criteria = null,
        #[Max(255)]
        public ?string $iconName = null,
        public ?bool $isActive = true,
    ) {}

    public function onlyModelAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'criteria' => $this->criteria,
            'icon_name' => $this->iconName,
            'is_active' => $this->isActive,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
