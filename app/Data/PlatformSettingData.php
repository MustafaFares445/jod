<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Max;

class PlatformSettingData extends Data
{
    public function __construct(
        #[Max(255)]
        public string $key,
        public mixed $value,
    ) {}
}
