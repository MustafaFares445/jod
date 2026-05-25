<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        // Sent notification
        Notification::create([
            'id' => 'notification-001',
            'creator_id' => 'user-123',
            'title' => 'New Campaign Submitted for Review',
            'body' => 'A new campaign has been submitted by Help Foundation and is awaiting your review',
            'category' => 'campaign',
            'mailbox' => 'sent',
            'recipient_scope' => 'all',
            'recipient_label' => 'All administrators',
            'priority' => 'normal',
            'status' => 'sent',
            'reference_label' => 'Emergency Medical Fund',
            'reference_path' => '/admin/campaigns/campaign-001',
            'created_at' => now()->subDays(1),
            'sent_at' => now()->subDays(1),
            'read_at' => null,
        ]);

        // Unread notification
        Notification::create([
            'id' => 'notification-002',
            'creator_id' => 'user-456',
            'title' => 'Post Approval Alert',
            'body' => 'Your post has been approved and published to the platform',
            'category' => 'post',
            'mailbox' => 'inbox',
            'recipient_scope' => 'organizations',
            'recipient_label' => 'Organization staff',
            'priority' => 'high',
            'status' => 'unread',
            'reference_label' => 'Emergency flood relief needed',
            'reference_path' => '/posts/post-001',
            'created_at' => now()->subHours(2),
            'sent_at' => now()->subHours(2),
            'read_at' => null,
        ]);

        // Read notification
        Notification::create([
            'id' => 'notification-003',
            'creator_id' => 'user-123',
            'title' => 'Report Submitted',
            'body' => 'A new report has been submitted and requires your attention',
            'category' => 'report',
            'mailbox' => 'inbox',
            'recipient_scope' => 'users',
            'recipient_label' => 'Administrators',
            'priority' => 'high',
            'status' => 'read',
            'reference_label' => 'Suspicious campaign activity',
            'reference_path' => '/admin/reports/report-001',
            'created_at' => now()->subDays(2),
            'sent_at' => now()->subDays(2),
            'read_at' => now()->subDays(1),
        ]);

        // System notification
        Notification::create([
            'id' => 'notification-004',
            'creator_id' => null,
            'title' => 'Platform Maintenance Scheduled',
            'body' => 'The platform will undergo maintenance on Friday at 10 PM for 2 hours',
            'category' => 'system',
            'mailbox' => 'sent',
            'recipient_scope' => 'all',
            'recipient_label' => 'All users and organizations',
            'priority' => 'high',
            'status' => 'sent',
            'reference_label' => null,
            'reference_path' => null,
            'created_at' => now()->subDays(3),
            'sent_at' => now()->subDays(3),
            'read_at' => null,
        ]);

        // Badge award notification
        Notification::create([
            'id' => 'notification-005',
            'creator_id' => null,
            'title' => 'Badge Awarded',
            'body' => 'You have earned the "Top Donor" badge for donating over $1000 to campaigns',
            'category' => 'badge',
            'mailbox' => 'sent',
            'recipient_scope' => 'users',
            'recipient_label' => 'User with badge',
            'priority' => 'normal',
            'status' => 'sent',
            'reference_label' => 'Top Donor',
            'reference_path' => '/badges/badge-001',
            'created_at' => now()->subDays(5),
            'sent_at' => now()->subDays(5),
            'read_at' => null,
        ]);
    }
}
