<?php

namespace Database\Factories;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationRoleFactory extends Factory
{
    protected $model = OrganizationRole::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'permissions' => [
                PermissionNameResolver::resolve(PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW),
                PermissionNameResolver::resolve(PermissionGroup::ORG_POST, PermissionAction::VIEW),
            ],
            'is_active' => true,
            'is_system' => false,
            'members_count' => $this->faker->numberBetween(0, 5),
        ];
    }
}
