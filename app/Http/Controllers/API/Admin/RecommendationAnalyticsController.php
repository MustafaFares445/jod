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
        $impressions = $count('post_view'); $opens = $count(['post_open','campaign_open']); $saves = $count('post_save');
        $helpOffers = $count('help_offer'); $applications = $count('volunteer_application'); $donations = $count('campaign_donation');
        $interested = $count('interested'); $notInterested = $count('not_interested'); $hidePost = $count('hide_post'); $hidePublisher = $count('hide_publisher');
        $fulfilled = Post::query()->where('help_status', 'fulfilled')->whereBetween('updated_at', [$from, $to])->count();
        $helpActions = $helpOffers + $applications + $donations;
        $views = (clone $base)->where('event_type', 'post_view');
        $uniquePublishers = (clone $views)->whereNotNull('publisher_id')->selectRaw("COUNT(DISTINCT CONCAT(COALESCE(publisher_type,''), ':', publisher_id)) as aggregate")->value('aggregate') ?? 0;
        $uniqueCategories = (clone $views)->whereNotNull('category_id')->distinct('category_id')->count('category_id');
        $topPublisher = (clone $views)->whereNotNull('publisher_id')->selectRaw('publisher_type, publisher_id, COUNT(*) as impressions')->groupBy('publisher_type','publisher_id')->orderByDesc('impressions')->first();
        $topCategory = (clone $views)->whereNotNull('category_id')->selectRaw('category_id, COUNT(*) as impressions')->groupBy('category_id')->orderByDesc('impressions')->first();
        $series = UserInteraction::query()->whereBetween('occurred_at', [$from, $to])->whereIn('event_type', ['post_view','post_open','campaign_open','help_offer','volunteer_application','campaign_donation'])->selectRaw('DATE(occurred_at) as day')->selectRaw("SUM(CASE WHEN event_type = 'post_view' THEN 1 ELSE 0 END) as impressions")->selectRaw("SUM(CASE WHEN event_type IN ('post_open','campaign_open') THEN 1 ELSE 0 END) as opens")->selectRaw("SUM(CASE WHEN event_type IN ('help_offer','volunteer_application','campaign_donation') THEN 1 ELSE 0 END) as help_actions")->groupBy(DB::raw('DATE(occurred_at)'))->orderBy('day')->get();
        $rate = static fn (int $value): float => $impressions > 0 ? round(($value / $impressions) * 100, 2) : 0;
        return response()->json(['data' => [
            'range' => ['from'=>$from->toDateString(),'to'=>$to->toDateString()],
            'kpis' => ['impressions'=>$impressions,'openRate'=>$rate($opens),'saveRate'=>$rate($saves),'helpOffers'=>$helpOffers,'applications'=>$applications,'donations'=>$donations,'fulfilledRequests'=>$fulfilled,'recommendationToHelpRate'=>$rate($helpActions)],
            'feedback' => ['interested'=>$interested,'interestedRate'=>$rate($interested),'notInterested'=>$notInterested,'notInterestedRate'=>$rate($notInterested),'hidePost'=>$hidePost,'hidePostRate'=>$rate($hidePost),'hidePublisher'=>$hidePublisher,'hidePublisherRate'=>$rate($hidePublisher)],
            'diversity' => ['uniquePublishersShown'=>(int)$uniquePublishers,'uniqueCategoriesShown'=>$uniqueCategories,'topPublisherImpressionShare'=>$impressions>0&&$topPublisher?round(((int)$topPublisher->impressions/$impressions)*100,2):0,'topCategoryImpressionShare'=>$impressions>0&&$topCategory?round(((int)$topCategory->impressions/$impressions)*100,2):0],
            'series' => $series,
        ]]);
    }
}
