<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentAudience: string
{
    case General = 'general';
    case Student = 'student';
}
