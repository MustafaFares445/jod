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
            'id' => 'applicant-001',
            'organization_id' => 'org-002',
            'campaign_id' => 'campaign-002',
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
            'assigned_to' => 'user-1001',
            'internal_notes' => 'Pending documents verification',
        ]);

        // Applicant 2
        Applicant::create([
            'id' => 'applicant-002',
            'organization_id' => 'org-002',
            'campaign_id' => 'campaign-002',
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
            'id' => 'applicant-003',
            'organization_id' => 'org-001',
            'campaign_id' => 'campaign-001',
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
            'assigned_to' => 'user-456',
            'internal_notes' => 'Urgent case - high priority',
        ]);

        // Applicant 4
        Applicant::create([
            'id' => 'applicant-004',
            'organization_id' => 'org-002',
            'campaign_id' => 'campaign-002',
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
            'assigned_to' => 'user-1001',
            'internal_notes' => 'Does not meet criteria for this campaign',
        ]);

        // Applicant 5
        Applicant::create([
            'id' => 'applicant-005',
            'organization_id' => 'org-001',
            'campaign_id' => 'campaign-001',
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
