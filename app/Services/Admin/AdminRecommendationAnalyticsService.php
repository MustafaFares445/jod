<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\HelpRequestStatus;
use App\Enums\PersonalizationEventType;
use App\Models\Post;
use App\Models\RecommendationImpression;
use App\Models\UserInteraction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminRecommendationAnalyticsService
{
    public function analytics(array $filters): array
    {
        $from = isset($filters['dateFrom'])
            ? Carbon::parse($filters['dateFrom'])->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = isset($filters['dateTo'])
            ? Carbon::parse($filters['dateTo'])->endOfDay()
            : now()->endOfDay();

        $impressions = $this->impressionsQuery($filters, $from, $to);
        $total = (clone $impressions)->count();
        $interactions = $this->attributedInteractionsQuery($filters, $from, $to);
        $fulfilledRequests = $this->attributedFulfilledRequestsQuery($filters, $from, $to)->count();

        $counts = collect([
            'opens' => PersonalizationEventType::PostOpen->value,
            'saves' => PersonalizationEventType::PostSave->value,
            'follows' => PersonalizationEventType::PublisherFollow->value,
            'notInterested' => PersonalizationEventType::NotInterested->value,
            'hides' => PersonalizationEventType::HidePost->value,
            'helpOffers' => PersonalizationEventType::HelpOffer->value,
            'applications' => PersonalizationEventType::VolunteerApplication->value,
            'donations' => PersonalizationEventType::CampaignDonation->value,
        ])->mapWithKeys(
            fn (string $event, string $key) => [
                $key => (clone $interactions)->where('event_type', $event)->count(),
            ],
        );

        $meaningful = (int) $counts['helpOffers']
            + (int) $counts['applications']
            + (int) $counts['donations'];
        $rate = fn (int $value): float => $total > 0
            ? round(($value / $total) * 100, 2)
            : 0.0;

        return [
            'period' => [
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
            ],
            'summary' => [
                'impressions' => $total,
                'opens' => (int) $counts['opens'],
                'openRate' => $rate((int) $counts['opens']),
                'saves' => (int) $counts['saves'],
                'saveRate' => $rate((int) $counts['saves']),
                'follows' => (int) $counts['follows'],
                'notInterested' => (int) $counts['notInterested'],
                'notInterestedRate' => $rate((int) $counts['notInterested']),
                'hides' => (int) $counts['hides'],
                'hideRate' => $rate((int) $counts['hides']),
                'helpOffers' => (int) $counts['helpOffers'],
                'applications' => (int) $counts['applications'],
                'donations' => (int) $counts['donations'],
                'fulfilledRequests' => $fulfilledRequests,
                'recommendationToHelpRate' => $rate($meaningful),
                'attributionMode' => 'same-user-subject-impression-v1',
            ],
            'timeseries' => (clone $impressions)
                ->selectRaw('DATE(shown_at) as date, COUNT(*) as impressions')
                ->groupBy(DB::raw('DATE(shown_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'impressions' => (int) $row->impressions,
                ]),
            'byFeedType' => (clone $impressions)
                ->selectRaw('feed_type, COUNT(*) as impressions')
                ->groupBy('feed_type')
                ->orderByDesc('impressions')
                ->get()
                ->map(fn ($row) => [
                    'feedType' => $row->feed_type,
                    'impressions' => (int) $row->impressions,
                ]),
            'byCategory' => (clone $impressions)
                ->whereNotNull('category_id')
                ->selectRaw('category_id, COUNT(*) as impressions')
                ->groupBy('category_id')
                ->orderByDesc('impressions')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'categoryId' => $row->category_id,
                    'impressions' => (int) $row->impressions,
                ]),
            'byPublisher' => (clone $impressions)
                ->whereNotNull('publisher_id')
                ->selectRaw('publisher_type, publisher_id, COUNT(*) as impressions')
                ->groupBy('publisher_type', 'publisher_id')
                ->orderByDesc('impressions')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'publisherType' => $row->publisher_type,
                    'publisherId' => $row->publisher_id,
                    'impressions' => (int) $row->impressions,
                ]),
        ];
    }

    private function impressionsQuery(array $filters, Carbon $from, Carbon $to): Builder
    {
        return RecommendationImpression::query()
            ->whereBetween('shown_at', [$from, $to])
            ->when(
                $filters['feedType'] ?? null,
                fn (Builder $query, $value) => $query->where('feed_type', $value),
            )
            ->when(
                $filters['categoryId'] ?? null,
                fn (Builder $query, $value) => $query->where('category_id', $value),
            )
            ->when(
                $filters['publisherId'] ?? null,
                fn (Builder $query, $value) => $query->where('publisher_id', $value),
            )
            ->when(
                $filters['city'] ?? null,
                fn (Builder $query, $value) => $query->where('city', 'like', '%'.$value.'%'),
            );
    }

    private function attributedInteractionsQuery(array $filters, Carbon $from, Carbon $to): Builder
    {
        return UserInteraction::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereExists(function ($query) use ($filters, $from, $to): void {
                $query
                    ->selectRaw('1')
                    ->from('recommendation_impressions as attributed_impressions')
                    ->whereColumn('attributed_impressions.user_id', 'user_interactions.user_id')
                    ->whereColumn('attributed_impressions.subject_type', 'user_interactions.subject_type')
                    ->whereColumn('attributed_impressions.subject_id', 'user_interactions.subject_id')
                    ->whereBetween('attributed_impressions.shown_at', [$from, $to])
                    ->whereColumn('attributed_impressions.shown_at', '<=', 'user_interactions.occurred_at')
                    ->when(
                        $filters['feedType'] ?? null,
                        fn ($inner, $value) => $inner->where('attributed_impressions.feed_type', $value),
                    )
                    ->when(
                        $filters['categoryId'] ?? null,
                        fn ($inner, $value) => $inner->where('attributed_impressions.category_id', $value),
                    )
                    ->when(
                        $filters['publisherId'] ?? null,
                        fn ($inner, $value) => $inner->where('attributed_impressions.publisher_id', $value),
                    )
                    ->when(
                        $filters['city'] ?? null,
                        fn ($inner, $value) => $inner->where('attributed_impressions.city', 'like', '%'.$value.'%'),
                    );
            });
    }

    private function attributedFulfilledRequestsQuery(array $filters, Carbon $from, Carbon $to): Builder
    {
        return Post::query()
            ->where('type', 'help_request')
            ->whereIn('help_status', [
                HelpRequestStatus::Fulfilled->value,
                HelpRequestStatus::PartiallyFulfilled->value,
            ])
            ->whereNotNull('fulfilled_at')
            ->whereBetween('fulfilled_at', [$from, $to])
            ->whereExists(function ($query) use ($filters, $from, $to): void {
                $query
                    ->selectRaw('1')
                    ->from('recommendation_impressions as fulfillment_impressions')
                    ->where('fulfillment_impressions.subject_type', 'post')
                    ->whereColumn('fulfillment_impressions.subject_id', 'posts.id')
                    ->whereBetween('fulfillment_impressions.shown_at', [$from, $to])
                    ->whereColumn('fulfillment_impressions.shown_at', '<=', 'posts.fulfilled_at')
                    ->when(
                        $filters['feedType'] ?? null,
                        fn ($inner, $value) => $inner->where('fulfillment_impressions.feed_type', $value),
                    )
                    ->when(
                        $filters['categoryId'] ?? null,
                        fn ($inner, $value) => $inner->where('fulfillment_impressions.category_id', $value),
                    )
                    ->when(
                        $filters['publisherId'] ?? null,
                        fn ($inner, $value) => $inner->where('fulfillment_impressions.publisher_id', $value),
                    )
                    ->when(
                        $filters['city'] ?? null,
                        fn ($inner, $value) => $inner->where('fulfillment_impressions.city', 'like', '%'.$value.'%'),
                    );
            });
    }
}
