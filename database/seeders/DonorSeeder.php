<?php

namespace Database\Seeders;

use App\Models\Donor;
use Illuminate\Database\Seeder;

class DonorSeeder extends Seeder
{
    public function run(): void
    {
        // Donor 1
        Donor::create([
            'id' => SeedIds::id('donors.ahmedMohammed'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'Ahmed Mohammed',
            'email' => 'ahmed@example.com',
            'phone' => '+962791234567',
            'campaign_title' => 'Emergency Medical Fund',
            'amount_or_type' => '500.00',
            'donated_at' => now()->subDays(5),
            'city' => 'Amman',
            'source' => 'website',
            'payment_method' => 'credit_card',
            'campaign_ref' => 'REF-2025-001',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'VIP donor - send thank you gift',
        ]);

        // Donor 2
        Donor::create([
            'id' => SeedIds::id('donors.fatimaHassan'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'Fatima Hassan',
            'email' => 'fatima@example.com',
            'phone' => '+962791234568',
            'campaign_title' => 'Emergency Medical Fund',
            'amount_or_type' => '1000.00',
            'donated_at' => now()->subDays(3),
            'city' => 'Zarqa',
            'source' => 'website',
            'payment_method' => 'bank_transfer',
            'campaign_ref' => 'REF-2025-002',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'Large donor - personal follow-up needed',
        ]);

        // Donor 3
        Donor::create([
            'id' => SeedIds::id('donors.mohammadHassan'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'Mohammad Hassan',
            'email' => 'mohammad@example.com',
            'phone' => '+962791234569',
            'campaign_title' => 'Back to School Initiative',
            'amount_or_type' => '250.00',
            'donated_at' => now()->subDays(2),
            'city' => 'Amman',
            'source' => 'mobile_app',
            'payment_method' => 'credit_card',
            'campaign_ref' => 'REF-2025-003',
            'assigned_to' => null,
            'internal_notes' => null,
        ]);

        // Donor 4
        Donor::create([
            'id' => SeedIds::id('donors.sarahWilliams'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'Sarah Williams',
            'email' => 'sarah@example.com',
            'phone' => '+962791234570',
            'campaign_title' => 'Emergency Medical Fund',
            'amount_or_type' => '2000.00',
            'donated_at' => now()->subDays(1),
            'city' => 'Amman',
            'source' => 'social_media',
            'payment_method' => 'credit_card',
            'campaign_ref' => 'REF-2025-004',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'International donor - send international receipt',
        ]);

        // Donor 5
        Donor::create([
            'id' => SeedIds::id('donors.aliAbdullah'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'Ali Abdullah',
            'email' => 'ali@example.com',
            'phone' => '+962791234571',
            'campaign_title' => 'Back to School Initiative',
            'amount_or_type' => '500.00',
            'donated_at' => now()->subHours(12),
            'city' => 'Irbid',
            'source' => 'direct',
            'payment_method' => 'cash',
            'campaign_ref' => 'REF-2025-005',
            'assigned_to' => null,
            'internal_notes' => 'Cash donation received at office',
        ]);
    }
}
