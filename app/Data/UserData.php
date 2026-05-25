<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $password = null,
        public ?string $userType = null,
        public ?string $status = null,
        public ?int $organizationId = null,
    ) {}

    public function onlyModelAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $this->password,
            'user_type' => $this->userType,
            'status' => $this->status,
            'organization_id' => $this->organizationId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
