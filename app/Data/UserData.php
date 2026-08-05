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
        public ?string $organizationId = null,
    ) {}

    /**
     * @param  list<string>  $preserveNullAttributes
     * @return array<string, mixed>
     */
    public function onlyModelAttributes(array $preserveNullAttributes = []): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $this->password,
            'user_type' => $this->userType,
            'status' => $this->status,
            'organization_id' => $this->organizationId,
        ], static fn (mixed $value, string $attribute): bool => $value !== null || in_array($attribute, $preserveNullAttributes, true), ARRAY_FILTER_USE_BOTH);
    }
}
