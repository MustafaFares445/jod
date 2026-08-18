<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Services\Permissions\OrganizationPermissionSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanyAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'organization_id' => SeedIds::id('organizations.techForGood'),
                'role_id' => SeedIds::id('roles.org3.owner'),
                'name' => 'حسن أحمد',
                'email' => 'hassan@techforgood.org',
                'phone' => '+962791234574',
            ],
            [
                'organization_id' => SeedIds::id('organizations.ammanCommunityGroup'),
                'role_id' => SeedIds::id('roles.org4.owner'),
                'name' => 'نور خليل',
                'email' => 'noor@ammangroup.org',
                'phone' => '+962791234575',
            ],
        ];

        $permissionSyncService = app(OrganizationPermissionSyncService::class);

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            if (! $user->exists) {
                $user->id = (string) Str::uuid();
            }

            $user->forceFill([
                'email' => $account['email'],
                'name' => $account['name'],
                'phone' => $account['phone'],
                'user_type' => 'general',
                'organization_id' => $account['organization_id'],
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => 'password',
                'last_active_at' => now(),
            ])->save();

            OrganizationRole::query()
                ->whereKey($account['role_id'])
                ->update(['members_count' => 1]);

            OrganizationStaff::query()->updateOrCreate(
                [
                    'organization_id' => $account['organization_id'],
                    'email' => $account['email'],
                ],
                [
                    'user_id' => $user->id,
                    'organization_role_id' => $account['role_id'],
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'status' => 'active',
                    'invited_at' => now(),
                    'accepted_at' => now(),
                ],
            );

            $permissionSyncService->syncForUser($user);
        }
    }
}
