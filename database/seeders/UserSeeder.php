<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'password';

        // Admin user
        User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'John Admin',
            'email' => 'admin@jod.com',
            'phone' => '+962791234567',
            'user_type' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(5),
            'last_active_at' => now(),
        ]);

        // Organization owner
        User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Sarah Owner',
            'email' => 'owner@helpfoundation.org',
            'phone' => '+962791234568',
            'user_type' => 'general',
            'organization_id' => 'org-001',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(3),
            'last_active_at' => now()->subHours(2),
        ]);

        // Volunteer user
        User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Ahmed Mohammed',
            'email' => 'ahmed@example.com',
            'phone' => '+962791234569',
            'user_type' => 'volunteer',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(2),
            'last_active_at' => now()->subDays(1),
        ]);

        // Donor user
        User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Fatima Hassan',
            'email' => 'fatima@example.com',
            'phone' => '+962791234570',
            'user_type' => 'donor',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(1),
            'last_active_at' => now(),
        ]);

        // Job seeker
        User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Mohammed Ali',
            'email' => 'mohammed@example.com',
            'phone' => '+962791234571',
            'user_type' => 'job_seeker',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subWeeks(3),
            'last_active_at' => now()->subHours(5),
        ]);

        // Staff member
        User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Leila Manager',
            'email' => 'manager@helpfoundation.org',
            'phone' => '+962791234572',
            'user_type' => 'general',
            'organization_id' => 'org-001',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => bcrypt($password),
            'created_at' => now()->subMonths(4),
            'last_active_at' => now()->subDays(1),
        ]);
    }
}
