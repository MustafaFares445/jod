<?php

declare(strict_types=1);

namespace App\Enums;

enum DonationStatus: string
{
    case Pending = 'pending';
    case Contacting = 'contacting';
    case Agreed = 'agreed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
