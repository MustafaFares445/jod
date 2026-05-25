<?php

namespace Database\Seeders;

use App\Models\Report;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        // New report
        Report::create([
            'id' => 'report-001',
            'reporter_id' => 'user-789',
            'assignee_id' => null,
            'title' => 'Suspicious campaign activity',
            'description' => 'Campaign claims are not matching actual activities on ground',
            'category' => 'fraud',
            'severity' => 'high',
            'entity_type' => 'campaign',
            'entity_id' => 'campaign-789',
            'status' => 'new',
            'evidence' => json_encode([
                ['type' => 'url', 'content' => 'https://example.com/campaign-update'],
                ['type' => 'text', 'content' => 'Campaign shows donations but no activity updates'],
            ]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now(), 'note' => 'Report submitted'],
            ]),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        // In progress report
        Report::create([
            'id' => 'report-002',
            'reporter_id' => 'user-456',
            'assignee_id' => 'user-123',
            'title' => 'Inappropriate post content',
            'description' => 'Post contains offensive language and inappropriate imagery',
            'category' => 'inappropriate',
            'severity' => 'high',
            'entity_type' => 'post',
            'entity_id' => 'post-567',
            'status' => 'in_progress',
            'evidence' => json_encode([
                ['type' => 'screenshot', 'content' => 'screenshot_001.jpg'],
            ]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDays(2), 'note' => 'Report submitted'],
                ['status' => 'in_progress', 'timestamp' => now()->subDays(1), 'note' => 'Assigned to admin for review'],
            ]),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(1),
        ]);

        // Waiting response
        Report::create([
            'id' => 'report-003',
            'reporter_id' => 'user-999',
            'assignee_id' => 'user-123',
            'title' => 'User impersonation attempt',
            'description' => 'User account appears to be impersonating another person',
            'category' => 'fraud',
            'severity' => 'critical',
            'entity_type' => 'user',
            'entity_id' => 'user-8888',
            'status' => 'waiting_response',
            'evidence' => json_encode([]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDays(3), 'note' => 'Report submitted'],
                ['status' => 'in_progress', 'timestamp' => now()->subDays(2), 'note' => 'Under investigation'],
                ['status' => 'waiting_response', 'timestamp' => now()->subDays(1), 'note' => 'Awaiting additional information'],
            ]),
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(1),
        ]);

        // Closed report
        Report::create([
            'id' => 'report-004',
            'reporter_id' => 'user-456',
            'assignee_id' => 'user-123',
            'title' => 'Spam post reported',
            'description' => 'Multiple spam posts from same user',
            'category' => 'spam',
            'severity' => 'medium',
            'entity_type' => 'post',
            'entity_id' => 'post-9999',
            'status' => 'closed',
            'evidence' => json_encode([]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDays(7), 'note' => 'Report submitted'],
                ['status' => 'in_progress', 'timestamp' => now()->subDays(6), 'note' => 'Under review'],
                ['status' => 'closed', 'timestamp' => now()->subDays(5), 'note' => 'User account suspended'],
            ]),
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(5),
        ]);

        // Low severity report
        Report::create([
            'id' => 'report-005',
            'reporter_id' => 'user-789',
            'assignee_id' => null,
            'title' => 'Typo in campaign description',
            'description' => 'Campaign has a spelling error in its description',
            'category' => 'other',
            'severity' => 'low',
            'entity_type' => 'campaign',
            'entity_id' => 'campaign-111',
            'status' => 'new',
            'evidence' => json_encode([]),
            'timeline' => json_encode([
                ['status' => 'new', 'timestamp' => now()->subDays(1), 'note' => 'Report submitted'],
            ]),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
    }
}
