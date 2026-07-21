<?php

namespace Database\Seeders;

use App\Models\OrganizationStaff;
use App\Services\Permissions\OrganizationPermissionSyncService;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => SeedIds::id('users.sarahAhmed'),
            'organization_role_id' => SeedIds::id('roles.org1.owner'),
            'name' => 'سارة أحمد',
            'email' => 'sarah@helpfoundation.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(8),
            'accepted_at' => now()->subMonths(8),
        ]);

        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => SeedIds::id('users.leilaManager'),
            'organization_role_id' => SeedIds::id('roles.org1.manager'),
            'name' => 'ليلى أحمد',
            'email' => 'manager@helpfoundation.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(4),
            'accepted_at' => now()->subMonths(4),
        ]);

        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => null,
            'organization_role_id' => SeedIds::id('roles.org1.editor'),
            'name' => 'أحمد حسن',
            'email' => 'ahmed@helpfoundation.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(2),
        ]);

        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => null,
            'organization_role_id' => SeedIds::id('roles.org1.viewer'),
            'name' => 'نور خليل',
            'email' => 'noor@helpfoundation.org',
            'status' => 'invited',
            'invited_at' => now()->subMonth(),
        ]);

        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'user_id' => SeedIds::id('users.fatimaHassan'),
            'organization_role_id' => SeedIds::id('roles.org2.owner'),
            'name' => 'فاطمة محمد',
            'email' => 'fatima@educationinitiative.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(12),
            'accepted_at' => now()->subMonths(12),
        ]);

        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'user_id' => null,
            'organization_role_id' => SeedIds::id('roles.org2.manager'),
            'name' => 'رانيا سالم',
            'email' => 'rania@educationinitiative.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(6),
        ]);

        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.techForGood'),
            'user_id' => SeedIds::id('users.mohammedAli'),
            'organization_role_id' => null,
            'name' => 'حسن أحمد',
            'email' => 'hassan@techforgood.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(2),
        ]);

        $permissionSyncService = app(OrganizationPermissionSyncService::class);

        OrganizationStaff::query()
            ->with('user')
            ->whereNotNull('user_id')
            ->get()
            ->each(function (OrganizationStaff $staff) use ($permissionSyncService): void {
                if ($staff->user !== null) {
                    $permissionSyncService->syncForUser($staff->user);
                }
            });
    }
}
