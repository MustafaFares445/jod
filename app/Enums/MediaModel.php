<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;

/**
 * Stable API-facing model names for the general media manager.
 */
enum MediaModel: string
{
    case ORGANIZATION = 'organization';
    case CAMPAIGN = 'campaign';
    case POST = 'post';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::ORGANIZATION => Organization::class,
            self::CAMPAIGN => Campaign::class,
            self::POST => Post::class,
        };
    }

    /** @return array<string, int> map of prop => max items */
    public function props(): array
    {
        return match ($this) {
            self::ORGANIZATION => ['logo' => 1],
            self::CAMPAIGN, self::POST => ['images' => 10],
        };
    }

    public function maxItems(string $prop): ?int
    {
        return $this->props()[$prop] ?? null;
    }
}
