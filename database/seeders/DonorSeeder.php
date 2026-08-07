<?php

namespace Database\Seeders;

use App\Models\Donor;
use Illuminate\Database\Seeder;

class DonorSeeder extends Seeder
{
    public function run(): void
    {
        Donor::create([
            'id' => SeedIds::id('donors.ahmedMohammed'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'أحمد محمد',
            'email' => 'ahmed@example.com',
            'phone' => '+962791234567',
            'campaign_title' => 'صندوق العلاج الطبي الطارئ',
            'amount_or_type' => '500.00',
            'donated_at' => now()->subDays(5),
            'city' => 'عمّان',
            'source' => 'website',
            'payment_method' => 'credit_card',
            'campaign_ref' => 'REF-2025-001',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'متبرع مهم، يُرجى إرسال هدية شكر.',
        ]);

        Donor::create([
            'id' => SeedIds::id('donors.fatimaHassan'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'فاطمة حسن',
            'email' => 'fatima@example.com',
            'phone' => '+962791234568',
            'campaign_title' => 'صندوق العلاج الطبي الطارئ',
            'amount_or_type' => '1000.00',
            'donated_at' => now()->subDays(3),
            'city' => 'الزرقاء',
            'source' => 'website',
            'payment_method' => 'bank_transfer',
            'campaign_ref' => 'REF-2025-002',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'تبرع كبير يحتاج إلى متابعة شخصية.',
        ]);

        Donor::create([
            'id' => SeedIds::id('donors.mohammadHassan'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'محمد حسن',
            'email' => 'mohammad@example.com',
            'phone' => '+962791234569',
            'campaign_title' => 'مبادرة العودة إلى المدارس',
            'amount_or_type' => '250.00',
            'donated_at' => now()->subDays(2),
            'city' => 'عمّان',
            'source' => 'mobile_app',
            'payment_method' => 'credit_card',
            'campaign_ref' => 'REF-2025-003',
            'assigned_to' => null,
            'internal_notes' => null,
        ]);

        Donor::create([
            'id' => SeedIds::id('donors.sarahWilliams'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'سارة وليامز',
            'email' => 'sarah@example.com',
            'phone' => '+962791234570',
            'campaign_title' => 'صندوق العلاج الطبي الطارئ',
            'amount_or_type' => '2000.00',
            'donated_at' => now()->subDay(),
            'city' => 'عمّان',
            'source' => 'social_media',
            'payment_method' => 'credit_card',
            'campaign_ref' => 'REF-2025-004',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'متبرعة دولية، يُرجى إرسال إيصال دولي.',
        ]);

        Donor::create([
            'id' => SeedIds::id('donors.aliAbdullah'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'علي عبد الله',
            'email' => 'ali@example.com',
            'phone' => '+962791234571',
            'campaign_title' => 'مبادرة العودة إلى المدارس',
            'amount_or_type' => '500.00',
            'donated_at' => now()->subHours(12),
            'city' => 'إربد',
            'source' => 'direct',
            'payment_method' => 'cash',
            'campaign_ref' => 'REF-2025-005',
            'assigned_to' => null,
            'internal_notes' => 'تم استلام التبرع النقدي في مكتب المؤسسة.',
        ]);
    }
}
