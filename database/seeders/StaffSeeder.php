<?php

namespace Database\Seeders;

use App\Models\OrganizationStaff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        // Owner - org-001
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => SeedIds::id('users.sarahAhmed'),
            'organization_role_id' => SeedIds::id('roles.org1.owner'),
            'name' => 'Sarah Ahmed',
            'email' => 'sarah@helpfoundation.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(8),
            'accepted_at' => now()->subMonths(8),
        ]);

        // Manager - org-001
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => SeedIds::id('users.leilaManager'),
            'organization_role_id' => SeedIds::id('roles.org1.manager'),
            'name' => 'Leila Manager',
            'email' => 'manager@helpfoundation.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(4),
            'accepted_at' => now()->subMonths(4),
        ]);

        // Editor - org-001
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => null,
            'organization_role_id' => SeedIds::id('roles.org1.editor'),
            'name' => 'Ahmed Hassan',
            'email' => 'ahmed@helpfoundation.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(2),
        ]);

        // Viewer - org-001
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'user_id' => null,
            'organization_role_id' => SeedIds::id('roles.org1.viewer'),
            'name' => 'Noor Khalil',
            'email' => 'noor@helpfoundation.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(1),
        ]);

        // Owner - org-002
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'user_id' => SeedIds::id('users.fatimaHassan'),
            'organization_role_id' => SeedIds::id('roles.org2.owner'),
            'name' => 'Fatima Mohammed',
            'email' => 'fatima@educationinitiative.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(12),
            'accepted_at' => now()->subMonths(12),
        ]);

        // Manager - org-002
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'user_id' => null,
            'organization_role_id' => SeedIds::id('roles.org2.manager'),
            'name' => 'Rania Salem',
            'email' => 'rania@educationinitiative.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(6),
        ]);

        // Owner - org-003
        OrganizationStaff::create([
            'organization_id' => SeedIds::id('organizations.techForGood'),
            'user_id' => SeedIds::id('users.mohammedAli'),
            'organization_role_id' => null,
            'name' => 'Hassan Ahmed',
            'email' => 'hassan@techforgood.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(2),
        ]);
    }
}
