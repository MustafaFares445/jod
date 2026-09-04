<?php

declare(strict_types=1);

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Weekends = 'weekends';
    case Evenings = 'evenings';
    case RemoteOnly = 'remote_only';
}
