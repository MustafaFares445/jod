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
use App\Services\RecommendationSettingsService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersonalizedFeedService
{
    public function __construct(private readonly RecommendationSettingsService $settings) {}

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
            ->limit((int) $this->settings->all()['candidateLimit'])
            ->get();

        $ranked = $this->rank($viewer, $candidates);

        return $this->paginator($ranked, $page, $perPage);
    }

    /** @return array<string, mixed> */
    public function inspect(User $viewer, Post $post): array
    {
        $preference = $viewer->preference()->first();
        $interest = UserCategoryInterest::query()
            ->where('user_id', $viewer->id)
            ->where('category_id', $post->category_id)
            ->first();
        $followsAuthor = PublisherFollow::query()
            ->where('follower_user_id', $viewer->id)
            ->where('target_type', PublisherFollow::TARGET_USER)
            ->where('target_id', (string) $post->author_id)
            ->exists();
        $followsOrganization = $post->organization_id !== null && PublisherFollow::query()
            ->where('follower_user_id', $viewer->id)
            ->where('target_type', PublisherFollow::TARGET_ORGANIZATION)
            ->where('target_id', (string) $post->organization_id)
            ->exists();
        $viewCount = UserInteraction::query()
            ->where('user_id', $viewer->id)
            ->where('event_type', 'post_view')
            ->where('subject_type', 'post')
            ->where('subject_id', (string) $post->id)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->count();

        $scored = $this->score(
            $viewer,
            $post,
            $preference?->intent,
            $preference?->preferred_city ?? $viewer->city,
            $interest,
            $followsAuthor,
            $followsOrganization,
            $viewCount,
        );

        $personalizationReasons = array_intersect($scored['reasons'], [
            'followed_publisher',
            'explicit_interest',
            'behavioral_interest',
            'same_city',
        ]);
        $isExploration = $personalizationReasons === [];

        $feedbackTypes = PostFeedback::query()
            ->where('user_id', $viewer->id)
            ->where('post_id', $post->id)
            ->pluck('type')
            ->all();
        $publisherHidden = HiddenPublisher::query()
            ->where('user_id', $viewer->id)
            ->where('publisher_type', $post->organization_id !== null ? 'organization' : 'user')
            ->where('publisher_id', (string) ($post->organization_id ?? $post->author_id))
            ->exists();

        $exclusions = [];
        if ($post->status !== 'published') $exclusions[] = 'not_published';
        if ($post->expires_at !== null && $post->expires_at->isPast()) $exclusions[] = 'expired';
        if (in_array(PostFeedback::TYPE_NOT_INTERESTED, $feedbackTypes, true)) $exclusions[] = 'not_interested';
        if (in_array(PostFeedback::TYPE_HIDE, $feedbackTypes, true)) $exclusions[] = 'hidden_post';
        if ($publisherHidden) $exclusions[] = 'hidden_publisher';

        return [
            'user' => [
                'id' => (string) $viewer->id,
                'name' => (string) $viewer->name,
                'intent' => $preference?->intent?->value ?? $preference?->intent,
                'preferredCity' => $preference?->preferred_city ?? $viewer->city,
            ],
            'post' => [
                'id' => (string) $post->id,
                'title' => (string) $post->title,
                'type' => (string) $post->type,
                'status' => (string) $post->status,
                'category' => $post->category ? ['id' => (string) $post->category->id, 'name' => (string) $post->category->name] : null,
                'publisher' => $post->organization
                    ? ['type' => 'organization', 'id' => (string) $post->organization->id, 'name' => (string) $post->organization->name]
                    : ['type' => 'user', 'id' => (string) $post->author_id, 'name' => (string) ($post->author?->name ?? '')],
                'location' => $post->location,
                'urgency' => $post->urgency?->value ?? $post->urgency ?? 'normal',
            ],
            'eligible' => $exclusions === [],
            'exclusions' => $exclusions,
            'score' => $scored['score'],
            'components' => $scored['components'],
            'reasons' => $scored['reasons'],
            'source' => $isExploration ? 'exploration' : 'for_you',
            'isExploration' => $isExploration,
            'feedbackRequested' => $isExploration,
            'signals' => [
                'followsAuthor' => $followsAuthor,
                'followsOrganization' => $followsOrganization,
                'explicitCategoryWeight' => (float) ($interest?->explicit_weight ?? 0),
                'behavioralCategoryWeight' => (float) ($interest?->behavioral_weight ?? 0),
                'viewsLast30Days' => $viewCount,
                'publisherHidden' => $publisherHidden,
            ],
        ];
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

        $ranked = $candidates
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

                $personalizationReasons = array_intersect($scored['reasons'], [
                    'followed_publisher',
                    'explicit_interest',
                    'behavioral_interest',
                    'same_city',
                ]);
                $isExploration = $personalizationReasons === [];

                return [
                    'contentType' => 'post',
                    'sortAt' => $post->published_at ?? $post->created_at,
                    'model' => $post,
                    'score' => $scored['score'],
                    'reasons' => $scored['reasons'],
                    'isExploration' => $isExploration,
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

        return $this->applyExplorationMix($ranked, (float) $this->settings->all()['explorationRatio']);
    }

    /**
     * @return array{score: float, reasons: array<int, string>, components: array<string, float>}
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
        $weights = $this->settings->all()['weights'];
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
            (float) $this->settings->all()['popularityCap'],
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
            'components' => $components,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $ranked */
    private function applyExplorationMix(Collection $ranked, float $ratio): Collection
    {
        $ratio = max(0.0, min($ratio, 0.5));
        if ($ratio <= 0 || $ranked->isEmpty()) {
            return $ranked;
        }

        $personalized = $ranked->where('isExploration', false)->values();
        $exploration = $ranked->where('isExploration', true)->values();
        if ($personalized->isEmpty() || $exploration->isEmpty()) {
            return $ranked;
        }

        $interval = max(2, (int) round(1 / $ratio));
        $mixed = collect();
        $personalizedIndex = 0;
        $explorationIndex = 0;
        $position = 1;

        while ($mixed->count() < $ranked->count()) {
            $useExploration = $position % $interval === 0 && $explorationIndex < $exploration->count();
            if ($useExploration) {
                $mixed->push($exploration[$explorationIndex++]);
            } elseif ($personalizedIndex < $personalized->count()) {
                $mixed->push($personalized[$personalizedIndex++]);
            } elseif ($explorationIndex < $exploration->count()) {
                $mixed->push($exploration[$explorationIndex++]);
            } else {
                break;
            }
            $position++;
        }

        return $mixed->values();
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
