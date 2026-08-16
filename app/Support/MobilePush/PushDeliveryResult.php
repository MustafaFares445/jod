<?php

declare(strict_types=1);

namespace App\Support\MobilePush;

final readonly class PushDeliveryResult
{
    private function __construct(
        public string $status,
        public ?string $providerMessageId = null,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self('sent', $providerMessageId);
    }

    public static function stale(): self
    {
        return new self('stale');
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isStale(): bool
    {
        return $this->status === 'stale';
    }
}
