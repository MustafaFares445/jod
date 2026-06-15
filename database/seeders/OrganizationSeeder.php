<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Organization 1: Help Foundation
        Organization::create([
            'id' => SeedIds::id('organizations.helpFoundation'),
            'name' => 'Help Foundation',
            'email' => 'contact@helpfoundation.org',
            'phone' => '+962796543210',
            'location' => 'Amman, Jordan',
            'short_address' => '123 Help Street, Amman',
            'organization_type' => 'ngo',
            'registration_number' => 'NGO-2023-001',
            'establishment_date' => '2020-03-15',
            'description' => 'Dedicated to providing humanitarian aid and support to those in need across Jordan',
            'license_document_name' => 'license_2023.pdf',
            'delegation_document_name' => 'delegation_2023.pdf',
            'owner_full_name' => 'Sarah Ahmed',
            'owner_email' => 'sarah@helpfoundation.org',
            'owner_phone' => '+962791234567',
            'website' => 'https://helpfoundation.org',
            'social_media' => json_encode([
                'facebook' => 'facebook.com/helpfoundation',
                'twitter' => '@helpfoundation',
                'instagram' => 'helpfoundation',
            ]),
            'status' => 'active',
            'verification_status' => 'verified',
            'accepted_at' => now()->subMonths(6),
            'created_at' => now()->subMonths(8),
            'last_active_at' => now()->subHours(3),
        ]);

        // Organization 2: Education Initiative
        Organization::create([
            'id' => SeedIds::id('organizations.educationInitiative'),
            'name' => 'Education Initiative',
            'email' => 'info@educationinitiative.org',
            'phone' => '+962796543211',
            'location' => 'Zarqa, Jordan',
            'short_address' => '456 Education Ave, Zarqa',
            'organization_type' => 'charity',
            'registration_number' => 'CHR-2023-002',
            'establishment_date' => '2019-07-20',
            'description' => 'Providing quality education access to underprivileged communities',
            'license_document_name' => 'license_ed_2023.pdf',
            'delegation_document_name' => 'delegation_ed_2023.pdf',
            'owner_full_name' => 'Fatima Mohammed',
            'owner_email' => 'fatima@educationinitiative.org',
            'owner_phone' => '+962791234573',
            'website' => 'https://educationinitiative.org',
            'social_media' => json_encode([
                'facebook' => 'facebook.com/educationinitiative',
                'twitter' => '@education_init',
                'linkedin' => 'education-initiative',
            ]),
            'status' => 'active',
            'verification_status' => 'verified',
            'accepted_at' => now()->subMonths(9),
            'created_at' => now()->subMonths(12),
            'last_active_at' => now()->subDays(2),
        ]);

        // Organization 3: Tech for Good (unverified)
        Organization::create([
            'id' => SeedIds::id('organizations.techForGood'),
            'name' => 'Tech for Good',
            'email' => 'hello@techforgood.org',
            'phone' => '+962796543212',
            'location' => 'Irbid, Jordan',
            'short_address' => '789 Tech Street, Irbid',
            'organization_type' => 'social_enterprise',
            'registration_number' => 'SE-2024-003',
            'establishment_date' => '2023-01-15',
            'description' => 'Using technology to solve social problems in Jordan',
            'license_document_name' => 'license_tech_2024.pdf',
            'delegation_document_name' => 'delegation_tech_2024.pdf',
            'owner_full_name' => 'Hassan Ahmed',
            'owner_email' => 'hassan@techforgood.org',
            'owner_phone' => '+962791234574',
            'website' => 'https://techforgood.org',
            'social_media' => json_encode([
                'github' => 'github.com/techforgood',
                'twitter' => '@techforgood_jo',
            ]),
            'status' => 'active',
            'verification_status' => 'unverified',
            'accepted_at' => null,
            'created_at' => now()->subMonths(2),
            'last_active_at' => now()->subDays(5),
        ]);

        // Organization 4: Community Group
        Organization::create([
            'id' => SeedIds::id('organizations.ammanCommunityGroup'),
            'name' => 'Amman Community Group',
            'email' => 'contact@ammangroup.org',
            'phone' => '+962796543213',
            'location' => 'Amman, Jordan',
            'short_address' => '321 Community Lane, Amman',
            'organization_type' => 'community_group',
            'registration_number' => 'CG-2024-004',
            'establishment_date' => '2023-06-01',
            'description' => 'Building stronger communities through grassroots initiatives',
            'license_document_name' => 'license_community_2024.pdf',
            'delegation_document_name' => 'delegation_community_2024.pdf',
            'owner_full_name' => 'Noor Khalil',
            'owner_email' => 'noor@ammangroup.org',
            'owner_phone' => '+962791234575',
            'website' => null,
            'social_media' => json_encode([
                'facebook' => 'facebook.com/ammangroup',
            ]),
            'status' => 'pending',
            'verification_status' => 'pending',
            'accepted_at' => null,
            'created_at' => now()->subWeeks(2),
            'last_active_at' => now()->subDays(10),
        ]);
    }
}
