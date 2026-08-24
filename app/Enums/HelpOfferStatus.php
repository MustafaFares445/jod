<?php

declare(strict_types=1);

namespace App\Enums;

enum HelpOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Contacting = 'contacting';
    case Agreed = 'agreed';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Accepted, self::Contacting, self::Agreed], true);
    }

    public function isProgressing(): bool
    {
        return in_array($this, [self::Accepted, self::Contacting, self::Agreed], true);
    }
}
