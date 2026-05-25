<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\BadgeData;
use App\Models\Badge;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    public function store(BadgeData $data): Badge
    {
        return DB::transaction(static function () use ($data) {
            return Badge::create($data->onlyModelAttributes());
        });
    }

    public function update(BadgeData $data, Badge $badge): Badge
    {
        return DB::transaction(static function () use ($data, $badge) {
            tap($badge)->update($data->onlyModelAttributes());
            return $badge;
        });
    }

    public function updateStatus(Badge $badge, bool $isActive): Badge
    {
        return DB::transaction(static function () use ($badge, $isActive) {
            tap($badge)->update(['is_active' => $isActive]);
            return $badge;
        });
    }
}
