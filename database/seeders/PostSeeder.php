<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        // Published post
        Post::create([
            'id' => 'post-001',
            'organization_id' => 'org-001',
            'author_id' => 'user-456',
            'title' => 'Emergency flood relief needed',
            'summary' => 'Our area has been hit by severe flooding. We urgently need supplies and volunteers.',
            'content' => 'Detailed content about flood relief efforts...',
            'type' => 'help_request',
            'status' => 'published',
            'location' => 'Amman',
            'campaign_id' => null,
            'views_count' => 1245,
            'reactions_count' => 87,
            'applications_count' => 12,
            'published_at' => now()->subWeeks(1),
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeeks(1),
        ]);

        // Pending post for review
        Post::create([
            'id' => 'post-002',
            'organization_id' => 'org-002',
            'author_id' => 'staff-001',
            'title' => 'Volunteer opportunity: Teacher needed',
            'summary' => 'We are looking for volunteer teachers for our summer program',
            'content' => 'Detailed content about teaching opportunity...',
            'type' => 'job_opportunity',
            'status' => 'pending',
            'location' => 'Zarqa',
            'campaign_id' => 'campaign-002',
            'views_count' => 0,
            'reactions_count' => 0,
            'applications_count' => 0,
            'published_at' => null,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // Approved post
        Post::create([
            'id' => 'post-003',
            'organization_id' => 'org-001',
            'author_id' => 'user-456',
            'title' => 'Medical Fund Update',
            'summary' => 'Update on how funds are being used for medical treatment',
            'content' => 'Detailed update on medical fund usage...',
            'type' => 'campaign_update',
            'status' => 'published',
            'location' => 'Amman',
            'campaign_id' => 'campaign-001',
            'views_count' => 2340,
            'reactions_count' => 156,
            'applications_count' => 34,
            'published_at' => now()->subWeeks(1),
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeeks(1),
        ]);

        // Archived post
        Post::create([
            'id' => 'post-004',
            'organization_id' => 'org-002',
            'author_id' => 'staff-001',
            'title' => 'Archived campaign announcement',
            'summary' => 'This is an old post that has been archived',
            'content' => 'Archived content...',
            'type' => 'campaign_teaser',
            'status' => 'archived',
            'location' => 'Zarqa',
            'campaign_id' => null,
            'views_count' => 500,
            'reactions_count' => 25,
            'applications_count' => 0,
            'published_at' => now()->subMonths(2),
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subMonths(1),
        ]);

        // Draft post
        Post::create([
            'id' => 'post-005',
            'organization_id' => 'org-003',
            'author_id' => 'user-999',
            'title' => 'Draft post not yet published',
            'summary' => 'This post is still being prepared',
            'content' => 'Draft content...',
            'type' => 'awareness',
            'status' => 'draft',
            'location' => 'Irbid',
            'campaign_id' => null,
            'views_count' => 0,
            'reactions_count' => 0,
            'applications_count' => 0,
            'published_at' => null,
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
    }
}
