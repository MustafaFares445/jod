<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OrganizationRole;
use App\Services\Permissions\OrganizationPermissionSyncService;
use Illuminate\Database\Seeder;

final class OrganizationRolePermissionSyncSeeder extends Seeder
{
    public function run(): void
    {
        $sync = app(OrganizationPermissionSyncService::class);

        OrganizationRole::query()
            ->where('is_active', true)
            ->orderBy('organization_id')
            ->orderBy('id')
            ->each(fn (OrganizationRole $role) => $sync->syncForRole($role));
    }
}
