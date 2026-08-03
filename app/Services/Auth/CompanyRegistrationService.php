<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Enums\PermissionModule;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Support\Facades\DB;

class CompanyRegistrationService
{
    /**
     * @param array<string, mixed> $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $organization = Organization::query()->create([
                'name' => $data['companyName'],
                'email' => $data['companyEmail'],
                'phone' => $data['companyPhone'],
                'organization_type' => $data['organizationType'],
                'registration_number' => $data['registrationNumber'],
                'location' => $data['location'],
                'description' => $data['description'] ?? null,
                'website' => $data['website'] ?? null,
                'establishment_date' => $data['establishmentDate'] ?? null,
                'owner_full_name' => $data['ownerName'],
                'owner_email' => $data['ownerEmail'],
                'owner_phone' => $data['ownerPhone'],
                'status' => 'pending',
                'verification_status' => 'pending',
                'last_active_at' => now(),
            ]);

            $user = User::query()->create([
                'name' => $data['ownerName'],
                'email' => $data['ownerEmail'],
                'phone' => $data['ownerPhone'],
                'password' => $data['password'],
                'user_type' => 'general',
                'organization_id' => $organization->id,
                'status' => 'active',
                'last_active_at' => now(),
            ]);

            $ownerRole = OrganizationRole::query()->create([
                'organization_id' => $organization->id,
                'name' => 'المالك',
                'description' => 'صلاحية كاملة لإدارة المؤسسة وجميع أقسامها.',
                'permissions' => array_merge(
                    [PermissionNameResolver::resolve(PermissionGroup::DASHBOARD, PermissionAction::VIEW)],
                    $this->organizationPermissionNames(),
                ),
                'is_active' => true,
                'is_system' => true,
                'members_count' => 1,
            ]);

            OrganizationStaff::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'organization_role_id' => $ownerRole->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'active',
                'invited_at' => now(),
                'accepted_at' => now(),
            ]);

            return $user->refresh();
        });
    }

    /** @return list<string> */
    private function organizationPermissionNames(): array
    {
        return PermissionCatalog::permissions()
            ->filter(fn (array $permission): bool => $permission['group']->module() === PermissionModule::ORGANIZATION)
            ->pluck('name')
            ->values()
            ->all();
    }
}
