<?php

namespace Database\Seeders;

use App\Models\OrganizationRole;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Owner role (system)
        OrganizationRole::create([
            'id' => 'role-001',
            'organization_id' => 'org-001',
            'name' => 'Owner',
            'description' => 'Full access to organization settings and all content',
            'permissions' => json_encode([
                'org.campaigns.view',
                'org.campaigns.create',
                'org.campaigns.update',
                'org.campaigns.delete',
                'org.posts.view',
                'org.posts.create',
                'org.posts.update',
                'org.posts.delete',
                'org.posts.publish',
                'org.staff.view',
                'org.staff.manage',
                'org.donors.view',
                'org.donors.manage',
                'org.applicants.view',
                'org.applicants.manage',
                'org.notifications.view',
                'org.notifications.send',
                'org.reports.view',
                'org.settings.view',
                'org.settings.update',
            ]),
            'is_active' => true,
            'is_system' => true,
            'members_count' => 1,
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subMonths(8),
        ]);

        // Manager role
        OrganizationRole::create([
            'id' => 'role-002',
            'organization_id' => 'org-001',
            'name' => 'Manager',
            'description' => 'Can manage campaigns, posts, and staff assignments',
            'permissions' => json_encode([
                'org.campaigns.view',
                'org.campaigns.create',
                'org.campaigns.update',
                'org.posts.view',
                'org.posts.create',
                'org.posts.update',
                'org.posts.publish',
                'org.staff.view',
                'org.donors.view',
                'org.donors.manage',
                'org.applicants.view',
                'org.applicants.manage',
                'org.notifications.view',
                'org.notifications.send',
                'org.reports.view',
            ]),
            'is_active' => true,
            'is_system' => false,
            'members_count' => 1,
            'created_at' => now()->subMonths(6),
            'updated_at' => now()->subMonths(6),
        ]);

        // Editor role
        OrganizationRole::create([
            'id' => 'role-003',
            'organization_id' => 'org-001',
            'name' => 'Editor',
            'description' => 'Can create and edit campaigns and posts',
            'permissions' => json_encode([
                'org.campaigns.view',
                'org.campaigns.create',
                'org.campaigns.update',
                'org.posts.view',
                'org.posts.create',
                'org.posts.update',
                'org.posts.publish',
                'org.donors.view',
                'org.applicants.view',
                'org.reports.view',
            ]),
            'is_active' => true,
            'is_system' => false,
            'members_count' => 1,
            'created_at' => now()->subMonths(4),
            'updated_at' => now()->subMonths(4),
        ]);

        // Viewer role (system)
        OrganizationRole::create([
            'id' => 'role-004',
            'organization_id' => 'org-001',
            'name' => 'Viewer',
            'description' => 'Can only view content and reports',
            'permissions' => json_encode([
                'org.campaigns.view',
                'org.posts.view',
                'org.donors.view',
                'org.applicants.view',
                'org.reports.view',
                'org.staff.view',
            ]),
            'is_active' => true,
            'is_system' => true,
            'members_count' => 1,
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subMonths(8),
        ]);

        // Contributor role
        OrganizationRole::create([
            'id' => 'role-005',
            'organization_id' => 'org-001',
            'name' => 'Contributor',
            'description' => 'Can submit posts for approval',
            'permissions' => json_encode([
                'org.campaigns.view',
                'org.posts.view',
                'org.posts.create',
                'org.donors.view',
                'org.reports.view',
            ]),
            'is_active' => true,
            'is_system' => false,
            'members_count' => 0,
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ]);

        // Organization 2 roles
        OrganizationRole::create([
            'id' => 'role-006',
            'organization_id' => 'org-002',
            'name' => 'Owner',
            'description' => 'Full access to organization',
            'permissions' => json_encode([
                'org.campaigns.view',
                'org.campaigns.create',
                'org.campaigns.update',
                'org.campaigns.delete',
                'org.posts.view',
                'org.posts.create',
                'org.posts.update',
                'org.posts.delete',
                'org.staff.view',
                'org.staff.manage',
            ]),
            'is_active' => true,
            'is_system' => true,
            'members_count' => 1,
            'created_at' => now()->subMonths(12),
            'updated_at' => now()->subMonths(12),
        ]);

        OrganizationRole::create([
            'id' => 'role-007',
            'organization_id' => 'org-002',
            'name' => 'Viewer',
            'description' => 'Read-only access',
            'permissions' => json_encode([
                'org.campaigns.view',
                'org.posts.view',
            ]),
            'is_active' => true,
            'is_system' => true,
            'members_count' => 0,
            'created_at' => now()->subMonths(12),
            'updated_at' => now()->subMonths(12),
        ]);
    }
}
