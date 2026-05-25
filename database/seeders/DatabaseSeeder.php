<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Permissions\PermissionsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(PermissionsSeeder::class);
        $this->call(OrganizationSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(DefaultOrganizationRolesSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(StaffSeeder::class);
        $this->call(BadgeSeeder::class);
        $this->call(CampaignSeeder::class);
        $this->call(NotificationSeeder::class);
        $this->call(PostSeeder::class);
        $this->call(ReportSeeder::class);
        $this->call(ArticleSeeder::class);
    }
}
