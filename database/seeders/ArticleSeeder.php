<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        Article::create([
            'id' => SeedIds::id('articles.howToStartSuccessfulCampaign'),
            'title' => 'كيف تبدأ حملة ناجحة',
            'slug' => 'how-to-start-successful-campaign',
            'excerpt' => 'نصائح وأفضل الممارسات لإطلاق حملة جمع تبرعات ناجحة.',
            'content' => 'دليل تفصيلي يوضح خطوات إعداد الحملة وإطلاقها ومتابعة نتائجها.',
            'author_id' => SeedIds::id('users.johnAdmin'),
            'status' => 'published',
            'published_at' => now()->subDays(5),
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(5),
        ]);

        Article::create([
            'id' => SeedIds::id('articles.volunteerSafetyGuidelines'),
            'title' => 'إرشادات سلامة المتطوعين',
            'slug' => 'volunteer-safety-guidelines',
            'excerpt' => 'إجراءات السلامة الأساسية التي يجب على كل متطوع الالتزام بها.',
            'content' => 'دليل شامل لإجراءات السلامة قبل وأثناء وبعد الأنشطة التطوعية.',
            'author_id' => SeedIds::id('users.johnAdmin'),
            'status' => 'published',
            'published_at' => now()->subDays(10),
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(10),
        ]);

        Article::create([
            'id' => SeedIds::id('articles.maximizingDonationImpact'),
            'title' => 'تعظيم أثر التبرعات',
            'slug' => 'maximizing-donation-impact',
            'excerpt' => 'كيف تضمن أن تحقق تبرعاتك أكبر أثر ممكن.',
            'content' => 'دليل يساعد المتبرعين على اختيار المبادرات وقياس أثر مساهماتهم.',
            'author_id' => SeedIds::id('users.sarahAhmed'),
            'status' => 'published',
            'published_at' => now()->subDays(15),
            'created_at' => now()->subDays(17),
            'updated_at' => now()->subDays(15),
        ]);

        Article::create([
            'id' => SeedIds::id('articles.buildingCommunityTrust'),
            'title' => 'بناء الثقة المجتمعية',
            'slug' => 'building-community-trust',
            'excerpt' => 'استراتيجيات تساعد المؤسسات على بناء الثقة مع مجتمعاتها.',
            'content' => 'ممارسات عملية لتعزيز الشفافية والتواصل والمشاركة المجتمعية.',
            'author_id' => SeedIds::id('users.johnAdmin'),
            'status' => 'draft',
            'published_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);

        Article::create([
            'id' => SeedIds::id('articles.digitalTransformationForNGOs'),
            'title' => 'التحول الرقمي للمؤسسات غير الربحية',
            'slug' => 'digital-transformation-ngos',
            'excerpt' => 'توظيف التقنية لتحسين عمليات المؤسسات غير الربحية.',
            'content' => 'دليل للتحول الرقمي واختيار الأدوات المناسبة وتحسين كفاءة العمل.',
            'author_id' => SeedIds::id('users.sarahAhmed'),
            'status' => 'published',
            'published_at' => now()->subMonth(),
            'created_at' => now()->subMonth()->subDays(2),
            'updated_at' => now()->subMonth(),
        ]);
    }
}
