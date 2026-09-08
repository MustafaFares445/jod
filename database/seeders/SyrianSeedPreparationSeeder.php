<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class SyrianSeedPreparationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'post_feedback',
            'recommendation_impressions',
            'user_interactions',
            'post_capabilities',
            'user_category_interests',
            'category_keywords',
            'post_likes',
            'saved_posts',
            'help_offers',
            'campaign_likes',
            'campaign_applications',
            'donations',
            'media',
            'posts',
            'campaigns',
            'organization_staff',
            'organization_roles',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('publisher_follows') && Schema::hasColumn('publisher_follows', 'target_type')) {
            DB::table('publisher_follows')->where('target_type', 'organization')->delete();
        }
        if (Schema::hasTable('hidden_publishers') && Schema::hasColumn('hidden_publishers', 'publisher_type')) {
            DB::table('hidden_publishers')->where('publisher_type', 'organization')->delete();
        }
        if (Schema::hasTable('organizations')) {
            DB::table('organizations')->delete();
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'organization_id')) {
            DB::table('users')->whereNotNull('organization_id')->update(['organization_id' => null, 'updated_at' => now()]);
        }
        if (Schema::hasTable('categories')) {
            DB::table('categories')->delete();
        }

        Storage::disk('public')->deleteDirectory('demo');
    }
}
