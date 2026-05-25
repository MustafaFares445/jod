<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;

class AnalyticsService
{
    public function getKpis(string $range = '7d'): array
    {
        $dates = $this->getDateRange($range);

        $newUsers = User::whereBetween('created_at', $dates)->count();
        $newPosts = Post::whereBetween('created_at', $dates)->count();
        $newCampaigns = Campaign::whereBetween('created_at', $dates)->count();

        $totalUsers = User::count();
        $totalPosts = Post::count();
        $totalCampaigns = Campaign::count();

        return [
            'kpis' => [
                [
                    'id' => 'new_users',
                    'label' => 'New Users',
                    'value' => $newUsers,
                    'changeVsLastMonth' => $this->calculateChange(User::class, $dates),
                ],
                [
                    'id' => 'new_posts',
                    'label' => 'New Posts',
                    'value' => $newPosts,
                    'changeVsLastMonth' => $this->calculateChange(Post::class, $dates),
                ],
                [
                    'id' => 'new_campaigns',
                    'label' => 'New Campaigns',
                    'value' => $newCampaigns,
                    'changeVsLastMonth' => $this->calculateChange(Campaign::class, $dates),
                ],
                [
                    'id' => 'total_users',
                    'label' => 'Total Users',
                    'value' => $totalUsers,
                    'changeVsLastMonth' => 0,
                ],
            ],
        ];
    }

    public function getWeeklyStats(string $range = '7d'): array
    {
        $dates = $this->getDateRange($range);
        $startDate = $dates[0];
        $endDate = $dates[1];

        $rows = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $weekStart = $current->copy()->startOfWeek();
            $weekEnd = $current->copy()->endOfWeek();

            $visits = Post::whereBetween('created_at', [$weekStart, $weekEnd])->count();
            $newUsers = User::whereBetween('created_at', [$weekStart, $weekEnd])->count();
            $donations = Campaign::whereBetween('created_at', [$weekStart, $weekEnd])->count();

            $rows[] = [
                'weekLabel' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'visits' => $visits,
                'newUsers' => $newUsers,
                'donations' => $donations,
            ];

            $current->addWeek();
        }

        return ['rows' => $rows];
    }

    private function getDateRange(string $range): array
    {
        $endDate = now();

        return match ($range) {
            '7d' => [now()->subDays(7), $endDate],
            '30d' => [now()->subDays(30), $endDate],
            '90d' => [now()->subDays(90), $endDate],
            '12m' => [now()->subYear(), $endDate],
            default => [now()->subDays(7), $endDate],
        };
    }

    private function calculateChange(string $model, array $dates): int
    {
        $before = $model::whereBetween('created_at', [
            $dates[0]->copy()->subMonthNoOverflow(),
            $dates[0],
        ])->count();

        $after = $model::whereBetween('created_at', $dates)->count();

        return $before > 0 ? round((($after - $before) / $before) * 100) : 0;
    }
}
