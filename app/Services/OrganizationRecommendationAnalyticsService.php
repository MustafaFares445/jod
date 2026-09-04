<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PersonalizationEventType;
use App\Models\Campaign;
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
        $followers = UserInteraction::query()->whereBetween('occurred_at', [$from, $to])
            ->where('event_type', PersonalizationEventType::PublisherFollow->value)
            ->where('publisher_id', $organizationId)->count();
        $meaningful = $counts['helpOffers'] + $counts['applications'] + $counts['donations'] + $counts['contacts'];
        $rate = fn (int $value): float => $total > 0 ? round(($value / $total) * 100, 2) : 0.0;

        return [
            'period' => ['dateFrom' => $from->toDateString(), 'dateTo' => $to->toDateString()],
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
            ],
            'timeseries' => (clone $impressions)->selectRaw('DATE(shown_at) as date, COUNT(*) as impressions')->groupBy(DB::raw('DATE(shown_at)'))->orderBy('date')->get()->map(fn ($r) => ['date' => $r->date, 'impressions' => (int) $r->impressions])->values(),
            'byCategory' => (clone $impressions)->whereNotNull('category_id')->selectRaw('category_id, COUNT(*) as impressions')->groupBy('category_id')->orderByDesc('impressions')->limit(20)->get()->map(fn ($r) => ['categoryId' => $r->category_id, 'impressions' => (int) $r->impressions])->values(),
            'topContent' => $this->contentRows($organizationId, $filters, $from, $to, 10),
        ];
    }

    public function contentPerformance(string $organizationId, array $filters): array
    {
        [$from, $to] = $this->period($filters);
        return ['period' => ['dateFrom' => $from->toDateString(), 'dateTo' => $to->toDateString()], 'data' => $this->contentRows($organizationId, $filters, $from, $to, 100)];
    }

    private function contentRows(string $organizationId, array $filters, Carbon $from, Carbon $to, int $limit): Collection
    {
        $rows = $this->impressions($organizationId, $filters, $from, $to)
            ->selectRaw('subject_type, subject_id, category_id, COUNT(*) as impressions')
            ->groupBy('subject_type', 'subject_id', 'category_id')->orderByDesc('impressions')->limit($limit)->get();
        $postIds = $rows->where('subject_type', 'post')->pluck('subject_id');
        $campaignIds = $rows->where('subject_type', 'campaign')->pluck('subject_id');
        $posts = Post::query()->where('organization_id', $organizationId)->whereIn('id', $postIds)->get(['id', 'title', 'type'])->keyBy('id');
        $campaigns = Campaign::query()->where('organization_id', $organizationId)->whereIn('id', $campaignIds)->get(['id', 'title'])->keyBy('id');

        return $rows->map(function ($row) use ($organizationId, $filters, $from, $to, $posts, $campaigns): array {
            $interactions = $this->attributedInteractions($organizationId, $filters, $from, $to)->where('subject_type', $row->subject_type)->where('subject_id', $row->subject_id);
            $counts = $this->interactionCounts($interactions);
            $actions = $counts['helpOffers'] + $counts['applications'] + $counts['donations'] + $counts['contacts'];
            $model = $row->subject_type === 'campaign' ? $campaigns->get($row->subject_id) : $posts->get($row->subject_id);
            return [
                'id' => (string) $row->subject_id,
                'contentType' => (string) $row->subject_type,
                'title' => (string) ($model?->title ?? ''),
                'postType' => $row->subject_type === 'post' ? $model?->type : null,
                'categoryId' => $row->category_id,
                'impressions' => (int) $row->impressions,
                'opens' => $counts['opens'], 'saves' => $counts['saves'],
                'helpOffers' => $counts['helpOffers'], 'applications' => $counts['applications'], 'donations' => $counts['donations'], 'contacts' => $counts['contacts'],
                'actions' => $actions,
                'conversionRate' => (int) $row->impressions > 0 ? round(($actions / (int) $row->impressions) * 100, 2) : 0.0,
            ];
        })->values();
    }

    private function interactionCounts(Builder $query): array
    {
        $events = [
            'opens' => PersonalizationEventType::PostOpen->value,
            'saves' => PersonalizationEventType::PostSave->value,
            'helpOffers' => PersonalizationEventType::HelpOffer->value,
            'applications' => PersonalizationEventType::VolunteerApplication->value,
            'donations' => PersonalizationEventType::CampaignDonation->value,
            'contacts' => PersonalizationEventType::ContactAction->value,
        ];
        return collect($events)->mapWithKeys(fn (string $event, string $key) => [$key => (clone $query)->where('event_type', $event)->count()])->all();
    }

    private function impressions(string $organizationId, array $filters, Carbon $from, Carbon $to): Builder
    {
        return RecommendationImpression::query()->whereBetween('shown_at', [$from, $to])->where('publisher_type', 'organization')->where('publisher_id', $organizationId)
            ->when($filters['contentType'] ?? null, fn (Builder $q, $v) => $q->where('subject_type', $v))
            ->when($filters['categoryId'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['postType'] ?? null, function (Builder $q, $v) use ($organizationId): void {
                $q->where('subject_type', 'post')->whereIn('subject_id', Post::query()->where('organization_id', $organizationId)->where('type', $v)->select('id'));
            });
    }

    private function attributedInteractions(string $organizationId, array $filters, Carbon $from, Carbon $to): Builder
    {
        return UserInteraction::query()->whereBetween('occurred_at', [$from, $to])->whereExists(function ($query) use ($organizationId, $filters, $from, $to): void {
            $query->selectRaw('1')->from('recommendation_impressions as oi')
                ->whereColumn('oi.user_id', 'user_interactions.user_id')
                ->whereColumn('oi.subject_type', 'user_interactions.subject_type')
                ->whereColumn('oi.subject_id', 'user_interactions.subject_id')
                ->where('oi.publisher_type', 'organization')->where('oi.publisher_id', $organizationId)
                ->whereBetween('oi.shown_at', [$from, $to])->whereColumn('oi.shown_at', '<=', 'user_interactions.occurred_at')
                ->when($filters['contentType'] ?? null, fn ($q, $v) => $q->where('oi.subject_type', $v))
                ->when($filters['categoryId'] ?? null, fn ($q, $v) => $q->where('oi.category_id', $v))
                ->when($filters['postType'] ?? null, function ($q, $v) use ($organizationId): void {
                    $q->where('oi.subject_type', 'post')->whereIn('oi.subject_id', Post::query()->where('organization_id', $organizationId)->where('type', $v)->select('id'));
                });
        });
    }

    private function period(array $filters): array
    {
        return [
            isset($filters['dateFrom']) ? Carbon::parse($filters['dateFrom'])->startOfDay() : now()->subDays(29)->startOfDay(),
            isset($filters['dateTo']) ? Carbon::parse($filters['dateTo'])->endOfDay() : now()->endOfDay(),
        ];
    }
}
