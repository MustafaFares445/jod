<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CapabilitySeeder::class);
        $this->call(JodCompleteDemoSeeder::class);
        $this->call(PersonalizationDemoSeeder::class);
        $this->call(OrganizationRolePermissionSyncSeeder::class);
    }
}
