<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        // Active campaign
        Campaign::create([
            'id' => 'campaign-001',
            'organization_id' => 'org-001',
            'creator_id' => 'user-456',
            'title' => 'Emergency Medical Fund',
            'summary' => 'Raising funds for emergency medical treatment for underprivileged children',
            'content' => 'Detailed content about medical fund campaign...',
            'category' => 'health',
            'status' => 'active',
            'location' => 'Amman',
            'goal_amount' => 50000,
            'raised_amount' => 35000,
            'beneficiaries_count' => 150,
            'donors_count' => 234,
            'applicants_count' => 45,
            'start_date' => now()->subMonths(1)->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subDays(1),
            'closed_at' => null,
            'closed_reason' => null,
        ]);

        // Active campaign 2
        Campaign::create([
            'id' => 'campaign-002',
            'organization_id' => 'org-002',
            'creator_id' => 'user-1001',
            'title' => 'Back to School Initiative',
            'summary' => 'Providing school supplies and uniforms for 500 students',
            'content' => 'Detailed content about back to school campaign...',
            'category' => 'education',
            'status' => 'active',
            'location' => 'Zarqa',
            'goal_amount' => 30000,
            'raised_amount' => 18500,
            'beneficiaries_count' => 500,
            'donors_count' => 167,
            'applicants_count' => 78,
            'start_date' => now()->addMonths(1)->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'created_at' => now()->subMonths(1),
            'updated_at' => now()->subDays(2),
            'closed_at' => null,
            'closed_reason' => null,
        ]);

        // Draft campaign
        Campaign::create([
            'id' => 'campaign-003',
            'organization_id' => 'org-001',
            'creator_id' => 'user-456',
            'title' => 'Food Security Program',
            'summary' => 'Ensuring food security for vulnerable families',
            'content' => 'Detailed content about food security...',
            'category' => 'food',
            'status' => 'draft',
            'location' => 'Amman',
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

        // Closed campaign
        Campaign::create([
            'id' => 'campaign-004',
            'organization_id' => 'org-003',
            'creator_id' => 'user-999',
            'title' => 'Emergency Relief 2024',
            'summary' => 'Closed emergency relief campaign',
            'content' => 'Campaign that has been completed...',
            'category' => 'emergency',
            'status' => 'closed',
            'location' => 'Irbid',
            'goal_amount' => 15000,
            'raised_amount' => 15200,
            'beneficiaries_count' => 100,
            'donors_count' => 89,
            'applicants_count' => 25,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonths(1)->toDateString(),
            'created_at' => now()->subMonths(4),
            'updated_at' => now()->subMonths(1),
            'closed_at' => now()->subMonths(1),
            'closed_reason' => 'Campaign goal reached and funds distributed',
        ]);

        // Pending campaign for review
        Campaign::create([
            'id' => 'campaign-005',
            'organization_id' => 'org-002',
            'creator_id' => 'user-1001',
            'title' => 'Shelter for Homeless',
            'summary' => 'Building shelter facilities for homeless individuals',
            'content' => 'Detailed shelter campaign content...',
            'category' => 'shelter',
            'status' => 'pending',
            'location' => 'Amman',
            'goal_amount' => 100000,
            'raised_amount' => 0,
            'beneficiaries_count' => 300,
            'donors_count' => 0,
            'applicants_count' => 0,
            'start_date' => now()->addMonths(3)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
            'closed_at' => null,
            'closed_reason' => null,
        ]);
    }
}
