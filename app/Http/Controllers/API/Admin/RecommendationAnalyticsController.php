<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\UserInteraction;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecommendationAnalyticsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::RECOMMENDATION, PermissionAction::VIEW)), 403);
        $from = Carbon::parse((string) $request->query('from', now()->subDays(29)->toDateString()))->startOfDay();
        $to = Carbon::parse((string) $request->query('to', now()->toDateString()))->endOfDay();

        $base = UserInteraction::query()->whereBetween('occurred_at', [$from, $to]);
        $count = static fn (string|array $events): int => (clone $base)->whereIn('event_type', (array) $events)->count();
        $impressions = $count('post_view');
        $opens = $count(['post_open', 'campaign_open']);
        $saves = $count('post_save');
        $helpOffers = $count('help_offer');
        $applications = $count('volunteer_application');
        $donations = $count('campaign_donation');
        $fulfilled = Post::query()->where('help_status', 'fulfilled')->whereBetween('updated_at', [$from, $to])->count();
        $helpActions = $helpOffers + $applications + $donations;

        $series = UserInteraction::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('event_type', ['post_view', 'post_open', 'campaign_open', 'help_offer', 'volunteer_application', 'campaign_donation'])
            ->selectRaw('DATE(occurred_at) as day')
            ->selectRaw("SUM(CASE WHEN event_type = 'post_view' THEN 1 ELSE 0 END) as impressions")
            ->selectRaw("SUM(CASE WHEN event_type IN ('post_open','campaign_open') THEN 1 ELSE 0 END) as opens")
            ->selectRaw("SUM(CASE WHEN event_type IN ('help_offer','volunteer_application','campaign_donation') THEN 1 ELSE 0 END) as help_actions")
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy('day')
            ->get();

        return response()->json(['data' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => [
                'impressions' => $impressions,
                'openRate' => $impressions > 0 ? round(($opens / $impressions) * 100, 2) : 0,
                'saveRate' => $impressions > 0 ? round(($saves / $impressions) * 100, 2) : 0,
                'helpOffers' => $helpOffers,
                'applications' => $applications,
                'donations' => $donations,
                'fulfilledRequests' => $fulfilled,
                'recommendationToHelpRate' => $impressions > 0 ? round(($helpActions / $impressions) * 100, 2) : 0,
            ],
            'series' => $series,
        ]]);
    }
}
