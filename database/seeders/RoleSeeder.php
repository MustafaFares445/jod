<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Enums\PermissionModule;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     permissions: list<string>,
     *     is_system: bool
     * }>
     */
    private function defaultRoles(): array
    {
        return [
            [
                'name' => 'Owner',
                'description' => 'Full access to organization management.',
                'permissions' => array_merge(
                    [PermissionNameResolver::resolve(PermissionGroup::DASHBOARD, PermissionAction::VIEW)],
                    $this->organizationPermissionNames(),
                ),
                'is_system' => true,
            ],
            [
                'name' => 'Manager',
                'description' => 'Can manage campaigns, posts, staff, and notifications.',
                'permissions' => $this->resolvePermissions([
                    [PermissionGroup::DASHBOARD, PermissionAction::VIEW],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::CREATE],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::UPDATE],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::CLOSE],
                    [PermissionGroup::ORG_POST, PermissionAction::VIEW],
                    [PermissionGroup::ORG_POST, PermissionAction::CREATE],
                    [PermissionGroup::ORG_POST, PermissionAction::UPDATE],
                    [PermissionGroup::ORG_POST, PermissionAction::PUBLISH],
                    [PermissionGroup::ORG_DONOR, PermissionAction::VIEW],
                    [PermissionGroup::ORG_DONOR, PermissionAction::MANAGE],
                    [PermissionGroup::ORG_APPLICANT, PermissionAction::VIEW],
                    [PermissionGroup::ORG_APPLICANT, PermissionAction::MANAGE],
                    [PermissionGroup::ORG_STAFF, PermissionAction::VIEW],
                    [PermissionGroup::ORG_STAFF, PermissionAction::MANAGE],
                    [PermissionGroup::ORG_NOTIFICATION, PermissionAction::VIEW],
                    [PermissionGroup::ORG_NOTIFICATION, PermissionAction::CREATE],
                    [PermissionGroup::ORG_NOTIFICATION, PermissionAction::SEND],
                    [PermissionGroup::ORG_REPORT, PermissionAction::VIEW],
                    [PermissionGroup::ORG_SETTINGS, PermissionAction::VIEW],
                ]),
                'is_system' => false,
            ],
            [
                'name' => 'Editor',
                'description' => 'Can create and edit campaigns and posts.',
                'permissions' => $this->resolvePermissions([
                    [PermissionGroup::DASHBOARD, PermissionAction::VIEW],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::CREATE],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::UPDATE],
                    [PermissionGroup::ORG_POST, PermissionAction::VIEW],
                    [PermissionGroup::ORG_POST, PermissionAction::CREATE],
                    [PermissionGroup::ORG_POST, PermissionAction::UPDATE],
                    [PermissionGroup::ORG_POST, PermissionAction::PUBLISH],
                    [PermissionGroup::ORG_DONOR, PermissionAction::VIEW],
                    [PermissionGroup::ORG_APPLICANT, PermissionAction::VIEW],
                    [PermissionGroup::ORG_REPORT, PermissionAction::VIEW],
                ]),
                'is_system' => false,
            ],
            [
                'name' => 'Viewer',
                'description' => 'Can only view organization data.',
                'permissions' => $this->resolvePermissions([
                    [PermissionGroup::DASHBOARD, PermissionAction::VIEW],
                    [PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW],
                    [PermissionGroup::ORG_POST, PermissionAction::VIEW],
                    [PermissionGroup::ORG_DONOR, PermissionAction::VIEW],
                    [PermissionGroup::ORG_APPLICANT, PermissionAction::VIEW],
                    [PermissionGroup::ORG_REPORT, PermissionAction::VIEW],
                ]),
                'is_system' => false,
            ],
        ];
    }

    public function run(): void
    {
        Organization::query()
            ->orderBy('id')
            ->each(function (Organization $organization): void {
                foreach ($this->defaultRoles() as $roleData) {
                    $role = OrganizationRole::query()->firstOrNew([
                        'organization_id' => $organization->id,
                        'name' => $roleData['name'],
                    ]);

                    if (! $role->exists) {
                        $role->id = (string) Str::uuid();
                    }

                    $role->fill([
                        'description' => $roleData['description'],
                        'permissions' => $roleData['permissions'],
                        'is_active' => true,
                        'is_system' => $roleData['is_system'],
                    ])->save();
                }
            });
    }

    /**
     * @return list<string>
     */
    private function organizationPermissionNames(): array
    {
        return PermissionCatalog::permissions()
            ->filter(fn (array $permission): bool => $permission['group']->module() === PermissionModule::ORGANIZATION)
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{0: PermissionGroup, 1: PermissionAction}>  $definitions
     * @return list<string>
     */
    private function resolvePermissions(array $definitions): array
    {
        return collect($definitions)
            ->map(fn (array $definition): string => PermissionNameResolver::resolve($definition[0], $definition[1]))
            ->values()
            ->all();
    }
}
