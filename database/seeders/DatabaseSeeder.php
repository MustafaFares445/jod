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
        $this->call(PersonalizationDemoSeeder::class);
        $this->call(StudentAssistanceSeedRefinementSeeder::class);
        $this->call(SyrianRealGroupsSeeder::class);

        if (! app()->environment('testing')) {
            $this->call(SyrianRealVideosSeeder::class);
            $this->call(RealSeedMediaRefinementSeeder::class);
        }

        $this->call(OrganizationRolePermissionSyncSeeder::class);
        $this->call(SyrianGeneralNotificationsSeeder::class);
        $this->call(SeedOperationalIdentifiersSeeder::class);
        $this->call(SeedDataPresentationSeeder::class);
        $this->call(RestoreAdminAccessSeeder::class);
        $this->call(PrimaryUserJourneySeeder::class);
    }
}
