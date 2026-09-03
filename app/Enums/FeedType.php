<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedType: string
{
    case ForYou = 'for_you';
    case Following = 'following';
    case Nearby = 'nearby';
    case Urgent = 'urgent';
}
