<?php

namespace Database\Seeders;

use App\Models\Applicant;
use Illuminate\Database\Seeder;

class ApplicantSeeder extends Seeder
{
    public function run(): void
    {
        // Applicant 1
        Applicant::create([
            'id' => SeedIds::id('applicants.leilaMohammed'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'Leila Mohammed',
            'email' => 'leila@example.com',
            'phone' => '+962791234572',
            'campaign_title' => 'Back to School',
            'amount_or_type' => 'Approved',
            'applied_at' => now()->subDays(10),
            'city' => 'Zarqa',
            'source' => 'internal',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-001',
            'assigned_to' => SeedIds::id('users.fatimaHassan'),
            'internal_notes' => 'Pending documents verification',
        ]);

        // Applicant 2
        Applicant::create([
            'id' => SeedIds::id('applicants.noorHassan'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'Noor Hassan',
            'email' => 'noor@example.com',
            'phone' => '+962791234573',
            'campaign_title' => 'Back to School',
            'amount_or_type' => 'Pending',
            'applied_at' => now()->subDays(5),
            'city' => 'Amman',
            'source' => 'website',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-002',
            'assigned_to' => null,
            'internal_notes' => 'Waiting for additional documentation',
        ]);

        // Applicant 3
        Applicant::create([
            'id' => SeedIds::id('applicants.omarSalem'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'Omar Salem',
            'email' => 'omar@example.com',
            'phone' => '+962791234574',
            'campaign_title' => 'Emergency Medical Fund',
            'amount_or_type' => 'Approved',
            'applied_at' => now()->subDays(15),
            'city' => 'Amman',
            'source' => 'phone',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-003',
            'assigned_to' => SeedIds::id('users.sarahAhmed'),
            'internal_notes' => 'Urgent case - high priority',
        ]);

        // Applicant 4
        Applicant::create([
            'id' => SeedIds::id('applicants.zainabAhmed'),
            'organization_id' => SeedIds::id('organizations.educationInitiative'),
            'campaign_id' => SeedIds::id('campaigns.backToSchoolInitiative'),
            'name' => 'Zainab Ahmed',
            'email' => 'zainab@example.com',
            'phone' => '+962791234575',
            'campaign_title' => 'Back to School',
            'amount_or_type' => 'Rejected',
            'applied_at' => now()->subDays(20),
            'city' => 'Zarqa',
            'source' => 'social_media',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-004',
            'assigned_to' => SeedIds::id('users.fatimaHassan'),
            'internal_notes' => 'Does not meet criteria for this campaign',
        ]);

        // Applicant 5
        Applicant::create([
            'id' => SeedIds::id('applicants.raniaHassan'),
            'organization_id' => SeedIds::id('organizations.helpFoundation'),
            'campaign_id' => SeedIds::id('campaigns.emergencyMedicalFund'),
            'name' => 'Rania Hassan',
            'email' => 'rania@example.com',
            'phone' => '+962791234576',
            'campaign_title' => 'Emergency Medical Fund',
            'amount_or_type' => 'Pending',
            'applied_at' => now()->subDays(3),
            'city' => 'Irbid',
            'source' => 'website',
            'payment_method' => null,
            'campaign_ref' => 'APP-2025-005',
            'assigned_to' => null,
            'internal_notes' => 'Recently submitted - under review',
        ]);
    }
}
