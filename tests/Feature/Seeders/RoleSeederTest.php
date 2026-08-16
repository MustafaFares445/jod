<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use Database\Seeders\Permissions\PermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SeedIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_synchronizes_assigned_users_with_complete_role_permissions(): void
    {
        $this->seed(PermissionsSeeder::class);

        $organization = Organization::factory()->create([
            'id' => SeedIds::id('organizations.helpFoundation'),
        ]);

        $this->seed(RoleSeeder::class);

        $role = OrganizationRole::query()->findOrFail(SeedIds::id('roles.org1.manager'));
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'user_type' => 'general',
        ]);

        OrganizationStaff::factory()->create([
            'organization_id' => $organization->id,
            'organization_role_id' => $role->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $user->syncPermissions([]);

        $this->seed(RoleSeeder::class);

        $this->assertEqualsCanonicalizing(
            $role->fresh()->permissions,
            $user->fresh()->getDirectPermissions()->pluck('name')->all(),
        );
    }
}
