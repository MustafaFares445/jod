<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionModule;
use App\Models\OrganizationRole;
use App\Services\Permissions\OrganizationPermissionSyncService;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Seeder;

final class OrganizationRolePermissionSyncSeeder extends Seeder
{
    public function run(): void
    {
        $ownerPermissions = PermissionCatalog::permissions()
            ->filter(fn (array $permission): bool =>
                $permission['group']->module() === PermissionModule::ORGANIZATION
                || str_contains($permission['name'], 'dashboard')
            )
            ->pluck('name')
            ->values()
            ->all();

        OrganizationRole::query()
            ->where('is_system', true)
            ->where('is_active', true)
            ->each(function (OrganizationRole $role) use ($ownerPermissions): void {
                $role->permissions = $ownerPermissions;
                $role->save();
            });

        $sync = app(OrganizationPermissionSyncService::class);
        OrganizationRole::query()
            ->where('is_active', true)
            ->orderBy('organization_id')
            ->orderBy('id')
            ->each(fn (OrganizationRole $role) => $sync->syncForRole($role));
    }
}
