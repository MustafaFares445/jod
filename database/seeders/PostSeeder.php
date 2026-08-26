<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        Post::create([
            'id' => SeedIds::id('posts.emergencyFloodRelief'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'author_id' => SeedIds::id('users.sarahAhmed'),
            'title' => 'مطلوب دعم عاجل لمتضرري الفيضانات',
            'summary' => 'تعرضت المنطقة لفيضانات شديدة ونحتاج بشكل عاجل إلى مستلزمات ومتطوعين.',
            'content' => 'تفاصيل جهود الإغاثة والاحتياجات العاجلة للأسر المتضررة من الفيضانات.',
            'type' => 'help_request',
            'status' => 'published',
            'location' => 'عمّان',
            'campaign_id' => null,
            'views_count' => 1245,
            'reactions_count' => 87,
            'applications_count' => 12,
            'published_at' => now()->subWeek(),
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeek(),
        ]);

        Post::create([
            'id' => SeedIds::id('posts.volunteerOpportunityTeacherNeeded'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'author_id' => SeedIds::id('users.leilaManager'),
            'title' => 'فرصة تطوع: مطلوب معلمون',
            'summary' => 'نبحث عن معلمين متطوعين للمشاركة في برنامجنا الصيفي.',
            'content' => 'تفاصيل فرصة التطوع والمتطلبات والجدول الزمني للبرنامج التعليمي.',
            'type' => 'job_opportunity',
            'status' => 'published',
            'location' => 'الزرقاء',
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'views_count' => 0,
            'reactions_count' => 0,
            'applications_count' => 0,
            'published_at' => now()->subDays(3),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        Post::create([
            'id' => SeedIds::id('posts.medicalFundUpdate'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'author_id' => SeedIds::id('users.sarahAhmed'),
            'title' => 'تحديث صندوق العلاج الطبي',
            'summary' => 'تحديث حول كيفية استخدام التبرعات في تغطية تكاليف العلاج الطبي.',
            'content' => 'تفاصيل الحالات التي تم دعمها وتوزيع مبالغ الصندوق خلال الفترة الماضية.',
            'type' => 'campaign_update',
            'status' => 'published',
            'location' => 'عمّان',
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'views_count' => 2340,
            'reactions_count' => 156,
            'applications_count' => 34,
            'published_at' => now()->subWeek(),
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeek(),
        ]);

        Post::create([
            'id' => SeedIds::id('posts.archivedCampaignAnnouncement'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'author_id' => SeedIds::id('users.leilaManager'),
            'title' => 'إعلان حملة مؤرشف',
            'summary' => 'هذا منشور قديم تمت أرشفته بعد انتهاء الحاجة إليه.',
            'content' => 'محتوى الإعلان المؤرشف.',
            'type' => 'campaign_teaser',
            'status' => 'archived',
            'location' => 'الزرقاء',
            'campaign_id' => null,
            'views_count' => 500,
            'reactions_count' => 25,
            'applications_count' => 0,
            'published_at' => now()->subMonths(2),
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subMonth(),
        ]);

        Post::create([
            'id' => SeedIds::id('posts.draftPostNotPublished'),
            'organization_id' => SeedIds::id('organizations.techForGood'),
            'author_id' => SeedIds::id('users.mohammedAli'),
            'title' => 'مسودة منشور غير منشور',
            'summary' => 'هذا المنشور ما يزال قيد الإعداد.',
            'content' => 'محتوى المسودة قيد المراجعة.',
            'type' => 'awareness',
            'status' => 'draft',
            'location' => 'إربد',
            'campaign_id' => null,
            'views_count' => 0,
            'reactions_count' => 0,
            'applications_count' => 0,
            'published_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }
}
