<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationRole;
use Illuminate\Database\Seeder;

class DefaultOrganizationRolesSeeder extends Seeder
{
    private const DEFAULT_ROLES = [
        [
            'name' => 'Owner',
            'description' => 'Full access to organization management',
            'permissions' => [
                'org.campaigns.*',
                'org.posts.*',
                'org.donors.*',
                'org.applicants.*',
                'org.staff.*',
                'org.roles.*',
                'org.notifications.*',
                'org.reports.view',
                'org.settings.update',
            ],
            'is_system' => true,
        ],
        [
            'name' => 'Manager',
            'description' => 'Can manage campaigns, posts, and staff',
            'permissions' => [
                'org.campaigns.view',
                'org.campaigns.create',
                'org.campaigns.update',
                'org.campaigns.close',
                'org.posts.view',
                'org.posts.create',
                'org.posts.update',
                'org.posts.publish',
                'org.donors.view',
                'org.applicants.view',
                'org.staff.view',
                'org.notifications.view',
                'org.notifications.create',
            ],
            'is_system' => false,
        ],
        [
            'name' => 'Editor',
            'description' => 'Can create and edit content',
            'permissions' => [
                'org.campaigns.view',
                'org.posts.view',
                'org.posts.create',
                'org.posts.update',
                'org.donors.view',
                'org.applicants.view',
            ],
            'is_system' => false,
        ],
        [
            'name' => 'Viewer',
            'description' => 'Can only view organization data',
            'permissions' => [
                'org.campaigns.view',
                'org.posts.view',
                'org.donors.view',
                'org.applicants.view',
            ],
            'is_system' => false,
        ],
    ];

    public function run(): void
    {
        Organization::query()->each(function (Organization $organization) {
            foreach (self::DEFAULT_ROLES as $roleData) {
                $organization->roles()->firstOrCreate(
                    ['name' => $roleData['name']],
                    [
                        'description' => $roleData['description'],
                        'permissions' => $roleData['permissions'],
                        'is_active' => true,
                        'is_system' => $roleData['is_system'],
                    ]
                );
            }
        });
    }
}
