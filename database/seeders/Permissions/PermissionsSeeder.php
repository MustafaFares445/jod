<?php

declare(strict_types=1);

namespace Database\Seeders\Permissions;

use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $now = now();

        $rows = PermissionCatalog::permissions()
            ->map(fn (array $permission): array => [
                'name' => $permission['name'],
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        Permission::query()->upsert(
            $rows,
            ['name', 'guard_name'],
            ['updated_at'],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
