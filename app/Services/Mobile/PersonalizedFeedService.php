<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\FeedType;
use App\Enums\PostUrgency;
use App\Enums\UserIntent;
use App\Models\HiddenPublisher;
use App\Models\Post;
use App\Models\PostFeedback;
use App\Models\PublisherFollow;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserInteraction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersonalizedFeedService
{
    public function paginate(User $viewer, FeedType $type, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $location = $viewer->preference?->preferred_city ?? $viewer->city;

        if ($type === FeedType::Nearby && blank($location)) {
            return $this->paginator(collect(), $page, $perPage);
        }

        $query = Post::query()
            ->with([
                'organization.logoMedia',
                'campaign',
                'category',
                'author.avatarMedia',
                'images',
                'videos',
                'likes' => fn ($query) => $query->where('user_id', $viewer->id),
                'saves' => fn ($query) => $query->where('user_id', $viewer->id),
            ])
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($type === FeedType::Nearby) {
            $query->where('location', $location);
        }

        if ($type === FeedType::Urgent) {
            $query->whereIn('urgency', [
                PostUrgency::Important->value,
                PostUrgency::Urgent->value,
                PostUrgency::Critical->value,
            ]);
        }

        $candidates = $query
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit((int) config('recommendations.candidate_limit', 200))
            ->get();

        $ranked = $this->rank($viewer, $candidates);

        return $this->paginator($ranked, $page, $perPage);
    }

    /** @param Collection<int, Post> $candidates */
    private function rank(User $viewer, Collection $candidates): Collection
    {
        $preference = $viewer->preference()->first();
        $interests = UserCategoryInterest::query()
            ->where('user_id', $viewer->id)
            ->get()
            ->keyBy('category_id');
        $follows = PublisherFollow::query()
            ->where('follower_user_id', $viewer->id)
            ->get();
        $followedUsers = $follows->where('target_type', PublisherFollow::TARGET_USER)->pluck('target_id')->flip();
        $followedOrganizations = $follows->where('target_type', PublisherFollow::TARGET_ORGANIZATION)->pluck('target_id')->flip();
        $excludedPostIds = PostFeedback::query()
            ->where('user_id', $viewer->id)
            ->whereIn('type', [PostFeedback::TYPE_NOT_INTERESTED, PostFeedback::TYPE_HIDE])
            ->pluck('post_id')
            ->flip();
        $hiddenPublishers = HiddenPublisher::query()
            ->where('user_id', $viewer->id)
            ->get()
            ->mapWithKeys(fn (HiddenPublisher $hidden): array => ["{$hidden->publisher_type}:{$hidden->publisher_id}" => true]);
        $viewCounts = UserInteraction::query()
            ->where('user_id', $viewer->id)
            ->where('event_type', 'post_view')
            ->where('subject_type', 'post')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->selectRaw('subject_id, COUNT(*) as aggregate')
            ->groupBy('subject_id')
            ->pluck('aggregate', 'subject_id');

        return $candidates
            ->reject(function (Post $post) use ($excludedPostIds, $hiddenPublishers): bool {
                if ($excludedPostIds->has((string) $post->id)) {
                    return true;
                }

                return $hiddenPublishers->has($this->publisherKey($post));
            })
            ->map(function (Post $post) use ($viewer, $preference, $interests, $followedUsers, $followedOrganizations, $viewCounts): array {
                $scored = $this->score(
                    $viewer,
                    $post,
                    $preference?->intent,
                    $preference?->preferred_city ?? $viewer->city,
                    $interests->get($post->category_id),
                    $followedUsers->has((string) $post->author_id),
                    $post->organization_id !== null && $followedOrganizations->has((string) $post->organization_id),
                    (int) ($viewCounts[$post->id] ?? 0),
                );

                return [
                    'contentType' => 'post',
                    'sortAt' => $post->published_at ?? $post->created_at,
                    'model' => $post,
                    'score' => $scored['score'],
                    'reasons' => $scored['reasons'],
                ];
            })
            ->sort(function (array $left, array $right): int {
                $scoreComparison = $right['score'] <=> $left['score'];
                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                return ($right['sortAt']?->getTimestamp() ?? 0) <=> ($left['sortAt']?->getTimestamp() ?? 0);
            })
            ->values();
    }

    /**
     * @return array{score: float, reasons: array<int, string>}
     */
    private function score(
        User $viewer,
        Post $post,
        ?UserIntent $intent,
        ?string $preferredCity,
        ?UserCategoryInterest $interest,
        bool $followsAuthor,
        bool $followsOrganization,
        int $viewCount,
    ): array {
        $weights = config('recommendations.weights');
        $components = [];

        if ($followsAuthor || $followsOrganization) {
            $components['followed_publisher'] = (float) $weights['followed_publisher'];
        }

        if ($interest?->explicit_weight > 0) {
            $components['explicit_interest'] = (float) $weights['explicit_interest'];
        }

        if (($interest?->behavioral_weight ?? 0) > 0) {
            $components['behavioral_interest'] = min(
                (float) $weights['behavioral_interest'],
                (float) $interest->behavioral_weight,
            );
        }

        if ($this->sameLocation($preferredCity, $post->location)) {
            $components['same_city'] = (float) $weights['same_city'];
        }

        if ($this->intentMatches($intent, $post)) {
            $components['intent_match'] = (float) $weights['intent_match'];
        }

        $freshness = $this->freshnessScore($post);
        if ($freshness > 0) {
            $components['fresh'] = $freshness;
        }

        $urgency = $this->urgencyScore($post);
        if ($urgency > 0) {
            $components['urgent'] = $urgency;
        }

        $popularity = min(
            (float) config('recommendations.popularity_cap', 10),
            floor(((int) $post->views_count + (int) $post->reactions_count) / 10),
        );
        if ($popularity > 0) {
            $components['popular_near_you'] = $popularity;
        }

        if ($viewCount >= 3) {
            $components['repeated_unengaged_view'] = (float) $weights['repeated_unengaged_view'];
        }

        $score = array_sum($components);
        $positiveComponents = collect($components)->filter(fn (float $value): bool => $value > 0)->sortDesc();

        return [
            'score' => $score,
            'reasons' => $positiveComponents->keys()->take(3)->values()->all(),
        ];
    }

    private function intentMatches(?UserIntent $intent, Post $post): bool
    {
        if ($intent === null || $intent === UserIntent::Both) {
            return true;
        }

        return match ($intent) {
            UserIntent::Giver => in_array($post->type, ['help_request', 'volunteer_opportunity', 'donation_campaign'], true),
            UserIntent::Receiver => in_array($post->type, ['service_offer', 'awareness', 'campaign_update'], true),
            UserIntent::Both => true,
        };
    }

    private function freshnessScore(Post $post): float
    {
        $publishedAt = $post->published_at ?? $post->created_at;
        if ($publishedAt === null) {
            return 0;
        }

        $hours = $publishedAt->diffInHours(now());

        return match (true) {
            $hours <= 6 => 10,
            $hours <= 24 => 8,
            $hours <= 72 => 5,
            $hours <= 168 => 2,
            default => 0,
        };
    }

    private function urgencyScore(Post $post): float
    {
        $urgency = $post->urgency?->value ?? $post->urgency ?? PostUrgency::Normal->value;

        return match ($urgency) {
            PostUrgency::Important->value => 4,
            PostUrgency::Urgent->value => 8,
            PostUrgency::Critical->value => 10,
            default => 0,
        };
    }

    private function sameLocation(?string $left, ?string $right): bool
    {
        if (blank($left) || blank($right)) {
            return false;
        }

        return Str::lower(trim($left)) === Str::lower(trim($right));
    }

    private function publisherKey(Post $post): string
    {
        if ($post->organization_id !== null) {
            return 'organization:'.$post->organization_id;
        }

        return 'user:'.$post->author_id;
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function paginator(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
