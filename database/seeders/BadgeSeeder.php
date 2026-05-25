<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        Badge::create([
            'id' => 'badge-001',
            'name' => 'Top Donor',
            'description' => 'Given to users who have donated over $1000',
            'criteria' => 'total_donations >= 1000',
            'icon_name' => 'star',
            'is_active' => true,
            'created_at' => now()->subMonths(6),
        ]);

        Badge::create([
            'id' => 'badge-002',
            'name' => 'Volunteer Champion',
            'description' => 'Given to volunteers with 50+ hours of service',
            'criteria' => 'volunteer_hours >= 50',
            'icon_name' => 'heart',
            'is_active' => true,
            'created_at' => now()->subMonths(5),
        ]);

        Badge::create([
            'id' => 'badge-003',
            'name' => 'Organization Leader',
            'description' => 'For organizations with 5+ successful campaigns',
            'criteria' => 'successful_campaigns >= 5',
            'icon_name' => 'medal',
            'is_active' => true,
            'created_at' => now()->subMonths(4),
        ]);

        Badge::create([
            'id' => 'badge-004',
            'name' => 'Early Supporter',
            'description' => 'For users who joined in the first month',
            'criteria' => 'joined_in_first_month = true',
            'icon_name' => 'rocket',
            'is_active' => true,
            'created_at' => now()->subMonths(3),
        ]);

        Badge::create([
            'id' => 'badge-005',
            'name' => 'Community Hero',
            'description' => 'For active community members with high engagement',
            'criteria' => 'community_score >= 100',
            'icon_name' => 'award',
            'is_active' => true,
            'created_at' => now()->subMonths(2),
        ]);
    }
}
