<?php

namespace Database\Seeders;

use App\Models\Applicant;
use Illuminate\Database\Seeder;

class ApplicantSeeder extends Seeder
{
    public function run(): void
    {
        Applicant::create([
            'id' => SeedIds::id('applicants.leilaMohammed'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'ليلى محمد',
            'email' => 'leila@example.com',
            'phone' => '+962791234572',
            'campaign_title' => 'مبادرة العودة إلى المدارس',
            'amount_or_type' => 'مقبول',
            'applied_at' => now()->subDays(10),
            'city' => 'الزرقاء',
            'source' => 'internal',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-001',
            'assigned_to' => SeedIds::id('users.fatimaHassan'),
            'internal_notes' => 'بانتظار التحقق من الوثائق.',
        ]);

        Applicant::create([
            'id' => SeedIds::id('applicants.noorHassan'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'نور حسن',
            'email' => 'noor@example.com',
            'phone' => '+962791234573',
            'campaign_title' => 'مبادرة العودة إلى المدارس',
            'amount_or_type' => 'قيد الانتظار',
            'applied_at' => now()->subDays(5),
            'city' => 'عمّان',
            'source' => 'website',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-002',
            'assigned_to' => null,
            'internal_notes' => 'بانتظار وثائق إضافية.',
        ]);

        Applicant::create([
            'id' => SeedIds::id('applicants.omarSalem'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'عمر سالم',
            'email' => 'omar@example.com',
            'phone' => '+962791234574',
            'campaign_title' => 'صندوق العلاج الطبي الطارئ',
            'amount_or_type' => 'مقبول',
            'applied_at' => now()->subDays(15),
            'city' => 'عمّان',
            'source' => 'phone',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-003',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'حالة عاجلة ذات أولوية مرتفعة.',
        ]);

        Applicant::create([
            'id' => SeedIds::id('applicants.zainabAhmed'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'زينب أحمد',
            'email' => 'zainab@example.com',
            'phone' => '+962791234575',
            'campaign_title' => 'مبادرة العودة إلى المدارس',
            'amount_or_type' => 'مرفوض',
            'applied_at' => now()->subDays(20),
            'city' => 'الزرقاء',
            'source' => 'social_media',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-004',
            'assigned_to' => SeedIds::id('users.fatimaHassan'),
            'internal_notes' => 'لا تنطبق عليها شروط الاستفادة من هذه الحملة.',
        ]);

        Applicant::create([
            'id' => SeedIds::id('applicants.raniaHassan'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'رانيا حسن',
            'email' => 'rania@example.com',
            'phone' => '+962791234576',
            'campaign_title' => 'صندوق العلاج الطبي الطارئ',
            'amount_or_type' => 'قيد الانتظار',
            'applied_at' => now()->subDays(3),
            'city' => 'إربد',
            'source' => 'website',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-005',
            'assigned_to' => null,
            'internal_notes' => 'طلب جديد قيد المراجعة.',
        ]);
    }
}
