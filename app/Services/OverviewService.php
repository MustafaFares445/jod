<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;

class OverviewService
{
    public function getStats(): array
    {
        return [
            'stats' => [
                [
                    'id' => 'total_users',
                    'label' => 'Total Users',
                    'value' => User::count(),
                    'subLabel' => 'Active users',
                    'icon' => 'users',
                ],
                [
                    'id' => 'total_organizations',
                    'label' => 'Organizations',
                    'value' => Organization::count(),
                    'subLabel' => 'Verified & active',
                    'icon' => 'building',
                ],
                [
                    'id' => 'pending_posts',
                    'label' => 'Pending Posts',
                    'value' => Post::where('status', 'pending')->count(),
                    'subLabel' => 'Awaiting review',
                    'icon' => 'document',
                ],
                [
                    'id' => 'pending_campaigns',
                    'label' => 'Pending Campaigns',
                    'value' => Campaign::where('status', 'pending')->count(),
                    'subLabel' => 'Awaiting review',
                    'icon' => 'flag',
                ],
                [
                    'id' => 'open_reports',
                    'label' => 'Open Reports',
                    'value' => Report::whereIn('status', ['new', 'in_progress'])->count(),
                    'subLabel' => 'Need attention',
                    'icon' => 'alert',
                ],
            ],
        ];
    }

    public function getActivity(): array
    {
        $activity = [];

        $recentPosts = Post::latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn(Post $post) => [
                'id' => "post-{$post->id}",
                'title' => "{$post->title}",
                'detail' => "New post submitted by organization",
                'at' => $post->created_at->toIso8601String(),
            ]);

        $recentCampaigns = Campaign::latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn(Campaign $campaign) => [
                'id' => "campaign-{$campaign->id}",
                'title' => "{$campaign->title}",
                'detail' => "New campaign submitted by organization",
                'at' => $campaign->created_at->toIso8601String(),
            ]);

        $recentReports = Report::latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn(Report $report) => [
                'id' => "report-{$report->id}",
                'title' => "{$report->title}",
                'detail' => "New report: {$report->status}",
                'at' => $report->created_at->toIso8601String(),
            ]);

        $activity = $recentPosts->concat($recentCampaigns)->concat($recentReports)
            ->sortByDesc('at')
            ->take(10)
            ->values()
            ->all();

        return ['activity' => $activity];
    }
}
