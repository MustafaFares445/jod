<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PersonalizationEventType;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Post;
use App\Models\RecommendationImpression;
use App\Models\UserInteraction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationRecommendationAnalyticsService
{
    public function analytics(string $organizationId, array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $impressions = $this->impressions($organizationId, $filters, $from, $to);
        $total = (clone $impressions)->count();
        $interactions = $this->attributedInteractions($organizationId, $filters, $from, $to);
        $counts = $this->interactionCounts($interactions);
        $followers = $this->attributedFollowerCount($organizationId, $filters, $from, $to);
        $meaningful = $counts['helpOffers'] + $counts['applications'] + $counts['donations'] + $counts['contacts'];
        $rate = fn (int $value): float => $total > 0 ? round(($value / $total) * 100, 2) : 0.0;

        return [
            'period' => [
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
            ],
            'summary' => [
                'impressions' => $total,
                'opens' => $counts['opens'],
                'openRate' => $rate($counts['opens']),
                'saves' => $counts['saves'],
                'saveRate' => $rate($counts['saves']),
                'newFollowers' => $followers,
                'helpOffers' => $counts['helpOffers'],
                'applications' => $counts['applications'],
                'donations' => $counts['donations'],
                'contacts' => $counts['contacts'],
                'recommendationToActionRate' => $rate($meaningful),
                'attributionMode' => 'same-user-content-impression-v1',
            ],
            'timeseries' => (clone $impressions)
                ->selectRaw('DATE(shown_at) as date, COUNT(*) as impressions')
                ->groupBy(DB::raw('DATE(shown_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'impressions' => (int) $row->impressions,
                ])
                ->values(),
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
                ])
                ->values(),
            'topContent' => $this->contentRows($organizationId, $filters, $from, $to, 10),
        ];
    }

    public function contentPerformance(string $organizationId, array $filters): array
    {
        [$from, $to] = $this->period($filters);

        return [
            'period' => [
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
            ],
            'data' => $this->contentRows($organizationId, $filters, $from, $to, 100),
        ];
    }

    private function contentRows(string $organizationId, array $filters, Carbon $from, Carbon $to, int $limit): Collection
    {
        $rows = $this->impressions($organizationId, $filters, $from, $to)
            ->selectRaw('subject_type, subject_id, category_id, COUNT(*) as impressions')
            ->groupBy('subject_type', 'subject_id', 'category_id')
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get();

        $postIds = $rows->where('subject_type', 'post')->pluck('subject_id');
        $campaignIds = $rows->where('subject_type', 'campaign')->pluck('subject_id');
        $videoIds = $rows->where('subject_type', 'video')->pluck('subject_id');

        $posts = Post::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $postIds)
            ->get(['id', 'title', 'type'])
            ->keyBy('id');
        $campaigns = Campaign::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $campaignIds)
            ->get(['id', 'title'])
            ->keyBy('id');
        $videos = Media::query()
            ->where('model_type', 'organization')
            ->where('model_id', $organizationId)
            ->where('prop', 'videos')
            ->whereIn('id', $videoIds)
            ->get(['id', 'description', 'original_name'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($organizationId, $filters, $from, $to, $posts, $campaigns, $videos): array {
            $interactions = $this->attributedInteractions($organizationId, $filters, $from, $to)
                ->where('subject_type', $row->subject_type)
                ->where('subject_id', $row->subject_id);
            $counts = $this->interactionCounts($interactions);
            $actions = $counts['helpOffers'] + $counts['applications'] + $counts['donations'] + $counts['contacts'];

            $model = match ((string) $row->subject_type) {
                'campaign' => $campaigns->get($row->subject_id),
                'video' => $videos->get($row->subject_id),
                default => $posts->get($row->subject_id),
            };
            $title = $row->subject_type === 'video'
                ? (string) ($model?->description ?: $model?->original_name ?: '')
                : (string) ($model?->title ?? '');

            return [
                'id' => (string) $row->subject_id,
                'contentType' => (string) $row->subject_type,
                'title' => $title,
                'postType' => $row->subject_type === 'post' ? $model?->type : null,
                'categoryId' => $row->category_id,
                'impressions' => (int) $row->impressions,
                'opens' => $counts['opens'],
                'saves' => $counts['saves'],
                'helpOffers' => $counts['helpOffers'],
                'applications' => $counts['applications'],
                'donations' => $counts['donations'],
                'contacts' => $counts['contacts'],
                'actions' => $actions,
                'conversionRate' => (int) $row->impressions > 0
                    ? round(($actions / (int) $row->impressions) * 100, 2)
                    : 0.0,
            ];
        })->values();
    }

    private function interactionCounts(Builder $query): array
    {
        return [
            'opens' => (clone $query)->whereIn('event_type', [
                PersonalizationEventType::PostOpen->value,
                PersonalizationEventType::CampaignOpen->value,
            ])->count(),
            'saves' => (clone $query)->where('event_type', PersonalizationEventType::PostSave->value)->count(),
            'helpOffers' => (clone $query)->where('event_type', PersonalizationEventType::HelpOffer->value)->count(),
            'applications' => (clone $query)->where('event_type', PersonalizationEventType::VolunteerApplication->value)->count(),
            'donations' => (clone $query)->where('event_type', PersonalizationEventType::CampaignDonation->value)->count(),
            'contacts' => (clone $query)->where('event_type', PersonalizationEventType::ContactAction->value)->count(),
        ];
    }

    private function impressions(string $organizationId, array $filters, Carbon $from, Carbon $to): Builder
    {
        return RecommendationImpression::query()
            ->whereBetween('shown_at', [$from, $to])
            ->where('publisher_type', 'organization')
            ->where('publisher_id', $organizationId)
            ->when($filters['contentType'] ?? null, fn (Builder $query, $value) => $query->where('subject_type', $value))
            ->when($filters['categoryId'] ?? null, fn (Builder $query, $value) => $query->where('category_id', $value))
            ->when($filters['postType'] ?? null, function (Builder $query, $value) use ($organizationId): void {
                $query->where('subject_type', 'post')
                    ->whereIn('subject_id', Post::query()
                        ->where('organization_id', $organizationId)
                        ->where('type', $value)
                        ->select('id'));
            });
    }

    private function attributedInteractions(string $organizationId, array $filters, Carbon $from, Carbon $to): Builder
    {
        return UserInteraction::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereExists(function ($query) use ($organizationId, $filters, $from, $to): void {
                $query->selectRaw('1')
                    ->from('recommendation_impressions as oi')
                    ->whereColumn('oi.user_id', 'user_interactions.user_id')
                    ->whereColumn('oi.subject_type', 'user_interactions.subject_type')
                    ->whereColumn('oi.subject_id', 'user_interactions.subject_id')
                    ->where('oi.publisher_type', 'organization')
                    ->where('oi.publisher_id', $organizationId)
                    ->whereBetween('oi.shown_at', [$from, $to])
                    ->whereColumn('oi.shown_at', '<=', 'user_interactions.occurred_at')
                    ->when($filters['contentType'] ?? null, fn ($inner, $value) => $inner->where('oi.subject_type', $value))
                    ->when($filters['categoryId'] ?? null, fn ($inner, $value) => $inner->where('oi.category_id', $value))
                    ->when($filters['postType'] ?? null, function ($inner, $value) use ($organizationId): void {
                        $inner->where('oi.subject_type', 'post')
                            ->whereIn('oi.subject_id', Post::query()
                                ->where('organization_id', $organizationId)
                                ->where('type', $value)
                                ->select('id'));
                    });
            });
    }

    private function attributedFollowerCount(string $organizationId, array $filters, Carbon $from, Carbon $to): int
    {
        return UserInteraction::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->where('event_type', PersonalizationEventType::PublisherFollow->value)
            ->where('publisher_id', $organizationId)
            ->whereExists(function ($query) use ($organizationId, $filters, $from, $to): void {
                $query->selectRaw('1')
                    ->from('recommendation_impressions as follower_impressions')
                    ->whereColumn('follower_impressions.user_id', 'user_interactions.user_id')
                    ->where('follower_impressions.publisher_type', 'organization')
                    ->where('follower_impressions.publisher_id', $organizationId)
                    ->whereBetween('follower_impressions.shown_at', [$from, $to])
                    ->whereColumn('follower_impressions.shown_at', '<=', 'user_interactions.occurred_at')
                    ->when($filters['contentType'] ?? null, fn ($inner, $value) => $inner->where('follower_impressions.subject_type', $value))
                    ->when($filters['categoryId'] ?? null, fn ($inner, $value) => $inner->where('follower_impressions.category_id', $value))
                    ->when($filters['postType'] ?? null, function ($inner, $value) use ($organizationId): void {
                        $inner->where('follower_impressions.subject_type', 'post')
                            ->whereIn('follower_impressions.subject_id', Post::query()
                                ->where('organization_id', $organizationId)
                                ->where('type', $value)
                                ->select('id'));
                    });
            })
            ->count();
    }

    /** @return array{Carbon, Carbon} */
    private function period(array $filters): array
    {
        return [
            isset($filters['dateFrom']) ? Carbon::parse($filters['dateFrom'])->startOfDay() : now()->subDays(29)->startOfDay(),
            isset($filters['dateTo']) ? Carbon::parse($filters['dateTo'])->endOfDay() : now()->endOfDay(),
        ];
    }
}
