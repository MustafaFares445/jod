<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        Badge::create([
            'id' => SeedIds::id('badges.topDonor'),
            'name' => 'كبير المتبرعين',
            'description' => 'تُمنح للمستخدمين الذين تجاوز مجموع تبرعاتهم 1000 دولار.',
            'criteria' => 'total_donations >= 1000',
            'icon_name' => 'star',
            'is_active' => true,
            'created_at' => now()->subMonths(6),
        ]);

        Badge::create([
            'id' => SeedIds::id('badges.volunteerChampion'),
            'name' => 'بطل التطوع',
            'description' => 'تُمنح للمتطوعين الذين أكملوا أكثر من 50 ساعة خدمة.',
            'criteria' => 'volunteer_hours >= 50',
            'icon_name' => 'heart',
            'is_active' => true,
            'created_at' => now()->subMonths(5),
        ]);

        Badge::create([
            'id' => SeedIds::id('badges.organizationLeader'),
            'name' => 'قائد المؤسسة',
            'description' => 'تُمنح للمؤسسات التي أنجزت خمس حملات ناجحة أو أكثر.',
            'criteria' => 'successful_campaigns >= 5',
            'icon_name' => 'medal',
            'is_active' => true,
            'created_at' => now()->subMonths(4),
        ]);

        Badge::create([
            'id' => SeedIds::id('badges.earlySupporter'),
            'name' => 'الداعم المبكر',
            'description' => 'تُمنح للمستخدمين الذين انضموا خلال الشهر الأول لإطلاق المنصة.',
            'criteria' => 'joined_in_first_month = true',
            'icon_name' => 'rocket',
            'is_active' => true,
            'created_at' => now()->subMonths(3),
        ]);

        Badge::create([
            'id' => SeedIds::id('badges.communityHero'),
            'name' => 'بطل المجتمع',
            'description' => 'تُمنح لأعضاء المجتمع النشطين ذوي التفاعل المرتفع.',
            'criteria' => 'community_score >= 100',
            'icon_name' => 'award',
            'is_active' => true,
            'created_at' => now()->subMonths(2),
        ]);
    }
}
