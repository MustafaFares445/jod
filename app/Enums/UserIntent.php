<?php

declare(strict_types=1);

namespace App\Enums;

enum UserIntent: string
{
    case Giver = 'giver';
    case Receiver = 'receiver';
    case Both = 'both';
}
