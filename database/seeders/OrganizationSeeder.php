<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::create([
            'id' => SeedIds::id('organizations.helpFoundation'),
            'name' => 'مؤسسة العون',
            'email' => 'contact@helpfoundation.org',
            'phone' => '+962796543210',
            'location' => 'عمّان، الأردن',
            'short_address' => 'شارع العون 123، عمّان',
            'organization_type' => 'ngo',
            'registration_number' => 'NGO-2023-001',
            'establishment_date' => '2020-03-15',
            'description' => 'مؤسسة متخصصة في تقديم المساعدات الإنسانية ودعم المحتاجين في مختلف مناطق الأردن.',
            'license_document_name' => 'license_2023.pdf',
            'delegation_document_name' => 'delegation_2023.pdf',
            'owner_full_name' => 'سارة أحمد',
            'owner_email' => 'sarah@helpfoundation.org',
            'owner_phone' => '+962791234567',
            'website' => 'https://helpfoundation.org',
            'social_media' => [
                'facebook' => 'facebook.com/helpfoundation',
                'twitter' => '@helpfoundation',
                'instagram' => 'helpfoundation',
            ],
            'status' => 'active',
            'verification_status' => 'verified',
            'accepted_at' => now()->subMonths(6),
            'created_at' => now()->subMonths(8),
            'last_active_at' => now()->subHours(3),
        ]);

        Organization::create([
            'id' => SeedIds::id('organizations.educationInitiative'),
            'name' => 'مبادرة التعليم',
            'email' => 'info@educationinitiative.org',
            'phone' => '+962796543211',
            'location' => 'الزرقاء، الأردن',
            'short_address' => 'شارع التعليم 456، الزرقاء',
            'organization_type' => 'charity',
            'registration_number' => 'CHR-2023-002',
            'establishment_date' => '2019-07-20',
            'description' => 'مبادرة تهدف إلى توفير تعليم نوعي للمجتمعات الأقل حظاً.',
            'license_document_name' => 'license_ed_2023.pdf',
            'delegation_document_name' => 'delegation_ed_2023.pdf',
            'owner_full_name' => 'فاطمة محمد',
            'owner_email' => 'fatima@educationinitiative.org',
            'owner_phone' => '+962791234573',
            'website' => 'https://educationinitiative.org',
            'social_media' => [
                'facebook' => 'facebook.com/educationinitiative',
                'twitter' => '@education_init',
                'linkedin' => 'education-initiative',
            ],
            'status' => 'active',
            'verification_status' => 'verified',
            'accepted_at' => now()->subMonths(9),
            'created_at' => now()->subMonths(12),
            'last_active_at' => now()->subDays(2),
        ]);

        Organization::create([
            'id' => SeedIds::id('organizations.techForGood'),
            'name' => 'التقنية من أجل الخير',
            'email' => 'hello@techforgood.org',
            'phone' => '+962796543212',
            'location' => 'إربد، الأردن',
            'short_address' => 'شارع التقنية 789، إربد',
            'organization_type' => 'social_enterprise',
            'registration_number' => 'SE-2024-003',
            'establishment_date' => '2023-01-15',
            'description' => 'مؤسسة تستخدم الحلول التقنية لمعالجة المشكلات المجتمعية في الأردن.',
            'license_document_name' => 'license_tech_2024.pdf',
            'delegation_document_name' => 'delegation_tech_2024.pdf',
            'owner_full_name' => 'حسن أحمد',
            'owner_email' => 'hassan@techforgood.org',
            'owner_phone' => '+962791234574',
            'website' => 'https://techforgood.org',
            'social_media' => [
                'github' => 'github.com/techforgood',
                'twitter' => '@techforgood_jo',
            ],
            'status' => 'active',
            'verification_status' => 'unverified',
            'accepted_at' => null,
            'created_at' => now()->subMonths(2),
            'last_active_at' => now()->subDays(5),
        ]);

        Organization::create([
            'id' => SeedIds::id('organizations.ammanCommunityGroup'),
            'name' => 'مجموعة مجتمع عمّان',
            'email' => 'contact@ammangroup.org',
            'phone' => '+962796543213',
            'location' => 'عمّان، الأردن',
            'short_address' => 'حي المجتمع 321، عمّان',
            'organization_type' => 'community_group',
            'registration_number' => 'CG-2024-004',
            'establishment_date' => '2023-06-01',
            'description' => 'مجموعة مجتمعية تعمل على تعزيز المبادرات المحلية وبناء مجتمعات أكثر ترابطاً.',
            'license_document_name' => 'license_community_2024.pdf',
            'delegation_document_name' => 'delegation_community_2024.pdf',
            'owner_full_name' => 'نور خليل',
            'owner_email' => 'noor@ammangroup.org',
            'owner_phone' => '+962791234575',
            'website' => null,
            'social_media' => [
                'facebook' => 'facebook.com/ammangroup',
            ],
            'status' => 'pending',
            'verification_status' => 'pending',
            'accepted_at' => null,
            'created_at' => now()->subWeeks(2),
            'last_active_at' => now()->subDays(10),
        ]);
    }
}
