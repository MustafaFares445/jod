<?php

declare(strict_types=1);

namespace App\Enums;

enum HelpRequestStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Fulfilled = 'fulfilled';
    case PartiallyFulfilled = 'partially_fulfilled';
    case NotFulfilled = 'not_fulfilled';
    case Expired = 'expired';
}
