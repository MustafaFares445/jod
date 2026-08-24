<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = collect([
            'health' => 'الصحة',
            'education' => 'التعليم',
            'food' => 'الغذاء',
            'emergency' => 'الطوارئ',
            'shelter' => 'الإيواء',
        ])->mapWithKeys(function (string $description, string $name): array {
            $category = Category::query()->firstOrCreate(
                ['name' => $name],
                [
                    'id' => (string) Str::uuid(),
                    'target' => 'campaign',
                    'description' => $description,
                    'status' => 'active',
                    'usage_count' => 0,
                ],
            );

            return [$name => $category->id];
        });

        Campaign::create([
            'id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'creator_id' => SeedIds::id('users.sarahAhmed'),
            'title' => 'صندوق العلاج الطبي الطارئ',
            'summary' => 'جمع التبرعات لتغطية العلاج الطبي الطارئ للأطفال من الأسر الأقل حظاً.',
            'content' => 'تفاصيل حملة صندوق العلاج الطبي وآلية دعم الحالات المستفيدة.',
            'category_id' => $categoryIds['health'],
            'status' => 'active',
            'location' => 'عمّان',
            'goal_amount' => 50000,
            'raised_amount' => 35000,
            'beneficiaries_count' => 150,
            'donors_count' => 234,
            'applicants_count' => 45,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subDay(),
            'closed_at' => null,
            'closed_reason' => null,
        ]);

        Campaign::create([
            'id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'creator_id' => SeedIds::id('users.fatimaHassan'),
            'title' => 'مبادرة العودة إلى المدارس',
            'summary' => 'توفير الحقائب والقرطاسية والزي المدرسي لخمسمائة طالب.',
            'content' => 'تفاصيل مبادرة العودة إلى المدارس وخطة توزيع المستلزمات على الطلبة.',
            'category_id' => $categoryIds['education'],
            'status' => 'active',
            'location' => 'الزرقاء',
            'goal_amount' => 30000,
            'raised_amount' => 18500,
            'beneficiaries_count' => 500,
            'donors_count' => 167,
            'applicants_count' => 78,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subDays(2),
            'closed_at' => null,
            'closed_reason' => null,
        ]);

        Campaign::create([
            'id' => SeedIds::id('campaigns.foodSecurityProgram'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'creator_id' => SeedIds::id('users.sarahAhmed'),
            'title' => 'برنامج الأمن الغذائي',
            'summary' => 'توفير الاحتياجات الغذائية الأساسية للأسر الأكثر احتياجاً.',
            'content' => 'تفاصيل برنامج الأمن الغذائي والفئات المستهدفة وآلية التوزيع.',
            'category_id' => $categoryIds['food'],
            'status' => 'draft',
            'location' => 'عمّان',
            'goal_amount' => 25000,
            'raised_amount' => 0,
            'beneficiaries_count' => 200,
            'donors_count' => 0,
            'applicants_count' => 0,
            'start_date' => now()->addMonths(2)->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
            'closed_at' => null,
            'closed_reason' => null,
        ]);

        Campaign::create([
            'id' => SeedIds::id('campaigns.emergencyRelief2024'),
            'organization_id' => SeedIds::id('organizations.techForGood'),
            'creator_id' => SeedIds::id('users.mohammedAli'),
            'title' => 'الإغاثة الطارئة 2024',
            'summary' => 'حملة إغاثة طارئة مكتملة لدعم الأسر المتضررة.',
            'content' => 'تفاصيل الحملة المكتملة ونتائج توزيع المساعدات على المستفيدين.',
            'category_id' => $categoryIds['emergency'],
            'status' => 'closed',
            'location' => 'إربد',
            'goal_amount' => 15000,
            'raised_amount' => 15200,
            'beneficiaries_count' => 100,
            'donors_count' => 89,
            'applicants_count' => 25,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'created_at' => now()->subMonths(4),
            'updated_at' => now()->subMonth(),
            'closed_at' => now()->subMonth(),
            'closed_reason' => 'تم تحقيق هدف الحملة وتوزيع المساعدات على المستفيدين.',
        ]);

        Campaign::create([
            'id' => SeedIds::id('campaigns.shelterForHomeless'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'creator_id' => SeedIds::id('users.fatimaHassan'),
            'title' => 'مأوى للأشخاص بلا سكن',
            'summary' => 'إنشاء مرافق إيواء آمنة للأشخاص الذين لا يملكون سكناً.',
            'content' => 'تفاصيل مشروع المأوى ومراحل التنفيذ والطاقة الاستيعابية المستهدفة.',
            'category_id' => $categoryIds['shelter'],
            'status' => 'pending',
            'location' => 'عمّان',
            'goal_amount' => 100000,
            'raised_amount' => 0,
            'beneficiaries_count' => 300,
            'donors_count' => 0,
            'applicants_count' => 0,
            'start_date' => now()->addMonths(3)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
            'closed_at' => null,
            'closed_reason' => null,
        ]);
    }
}
