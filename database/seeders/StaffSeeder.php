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
            'organization_id' => 'org-001',
            'user_id' => 'user-456',
            'organization_role_id' => 'role-001',
            'name' => 'Sarah Ahmed',
            'email' => 'sarah@helpfoundation.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(8),
            'accepted_at' => now()->subMonths(8),
        ]);

        // Manager - org-001
        OrganizationStaff::create([
            'organization_id' => 'org-001',
            'user_id' => 'staff-001',
            'organization_role_id' => 'role-002',
            'name' => 'Leila Manager',
            'email' => 'manager@helpfoundation.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(4),
            'accepted_at' => now()->subMonths(4),
        ]);

        // Editor - org-001
        OrganizationStaff::create([
            'organization_id' => 'org-001',
            'user_id' => null,
            'organization_role_id' => 'role-003',
            'name' => 'Ahmed Hassan',
            'email' => 'ahmed@helpfoundation.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(2),
        ]);

        // Viewer - org-001
        OrganizationStaff::create([
            'organization_id' => 'org-001',
            'user_id' => null,
            'organization_role_id' => 'role-004',
            'name' => 'Noor Khalil',
            'email' => 'noor@helpfoundation.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(1),
        ]);

        // Owner - org-002
        OrganizationStaff::create([
            'organization_id' => 'org-002',
            'user_id' => 'user-1001',
            'organization_role_id' => 'role-006',
            'name' => 'Fatima Mohammed',
            'email' => 'fatima@educationinitiative.org',
            'status' => 'active',
            'invited_at' => now()->subMonths(12),
            'accepted_at' => now()->subMonths(12),
        ]);

        // Manager - org-002
        OrganizationStaff::create([
            'organization_id' => 'org-002',
            'user_id' => null,
            'organization_role_id' => 'role-007',
            'name' => 'Rania Salem',
            'email' => 'rania@educationinitiative.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(6),
        ]);

        // Owner - org-003
        OrganizationStaff::create([
            'organization_id' => 'org-003',
            'user_id' => 'user-999',
            'organization_role_id' => null,
            'name' => 'Hassan Ahmed',
            'email' => 'hassan@techforgood.org',
            'status' => 'invited',
            'invited_at' => now()->subMonths(2),
        ]);
    }
}
