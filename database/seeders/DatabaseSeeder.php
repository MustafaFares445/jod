<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CapabilitySeeder::class);
        $this->call(SyrianSeedPreparationSeeder::class);
        $this->call(JodCompleteDemoSeeder::class);
        $this->call(SyrianSeedPreparationSeeder::class);
        $this->call(SyrianRealDemoSeeder::class);
        $this->call(NaturalizeSyrianSeedContentSeeder::class);
        $this->call(StudentAssistanceSeedRefinementSeeder::class);
        $this->call(PersonalizationDemoSeeder::class);
        $this->call(OrganizationRolePermissionSyncSeeder::class);
    }
}
