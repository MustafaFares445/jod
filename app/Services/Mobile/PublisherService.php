<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Organization;
use App\Models\User;

class PublisherService
{
    public function findPublic(string $id): Organization|User|null
    {
        $organization = Organization::query()
            ->whereKey($id)
            ->where('status', 'active')
            ->first();

        if ($organization !== null) {
            return $organization;
        }

        return User::query()
            ->whereKey($id)
            ->where('status', 'active')
            ->first();
    }
}
