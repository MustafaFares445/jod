<?php

declare(strict_types=1);

namespace App\Enums;

enum PostUrgency: string
{
    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';
    case Critical = 'critical';
}
