<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'password';

        $admin = User::create([
            'id' => SeedIds::id('users.johnAdmin'),
            'name' => 'مدير النظام',
            'email' => 'admin@jod.com',
            'phone' => '+962791234567',
            'user_type' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(5),
            'last_active_at' => now(),
        ]);

        $admin->syncPermissions(PermissionCatalog::names());

        User::create([
            'id' => SeedIds::id('users.sarahAhmed'),
            'name' => 'سارة أحمد',
            'email' => 'sarah@helpfoundation.org',
            'phone' => '+962791234568',
            'user_type' => 'general',
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(3),
            'last_active_at' => now()->subHours(2),
        ]);

        User::create([
            'id' => SeedIds::id('users.ahmedMohammed'),
            'name' => 'أحمد محمد',
            'email' => 'ahmed@example.com',
            'phone' => '+962791234569',
            'user_type' => 'volunteer',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(2),
            'last_active_at' => now()->subDay(),
        ]);

        User::create([
            'id' => SeedIds::id('users.fatimaHassan'),
            'name' => 'فاطمة محمد',
            'email' => 'fatima@educationinitiative.org',
            'phone' => '+962791234570',
            'user_type' => 'general',
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonth(),
            'last_active_at' => now(),
        ]);

        User::create([
            'id' => SeedIds::id('users.mohammedAli'),
            'name' => 'محمد علي',
            'email' => 'mohammed@example.com',
            'phone' => '+962791234571',
            'user_type' => 'job_seeker',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subWeeks(3),
            'last_active_at' => now()->subHours(5),
        ]);

        User::create([
            'id' => SeedIds::id('users.leilaManager'),
            'name' => 'ليلى أحمد',
            'email' => 'manager@helpfoundation.org',
            'phone' => '+962791234572',
            'user_type' => 'general',
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(4),
            'last_active_at' => now()->subDay(),
        ]);
    }
}
