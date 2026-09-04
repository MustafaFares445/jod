<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\AvailabilityStatus;
use App\Enums\FeedType;
use App\Enums\HelpRequestStatus;
use App\Enums\PersonalizationEventType;
use App\Enums\PostUrgency;
use App\Enums\UserIntent;
use App\Models\Campaign;
use App\Models\GroupMember;
use App\Models\HiddenPublisher;
use App\Models\Post;
use App\Models\PostFeedback;
use App\Models\PublisherFollow;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserExplorationCategoryState;
use App\Models\UserInteraction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersonalizedFeedService
{
    public function __construct(
        private readonly FeedDiversityService $diversity,
        private readonly InteractionTrackingService $interactions,
    ) {}

    public function paginate(User $viewer, FeedType $type, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $location = $viewer->preference?->preferred_city ?? $viewer->city;
        if ($type === FeedType::Nearby && blank($location)) return $this->paginator(collect(), $page, $perPage);

        $posts = Post::query()->with([
            'organization.logoMedia', 'campaign', 'category', 'requiredCapabilities', 'author.avatarMedia', 'images', 'videos',
            'likes' => fn ($query) => $query->where('user_id', $viewer->id),
            'saves' => fn ($query) => $query->where('user_id', $viewer->id),
        ])->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($query): void {
                $query->where('type', '!=', 'help_request')->orWhereNotIn('help_status', [
                    HelpRequestStatus::Fulfilled->value,
                    HelpRequestStatus::PartiallyFulfilled->value,
                    HelpRequestStatus::NotFulfilled->value,
                    HelpRequestStatus::Expired->value,
                ]);
            });

        if ($type === FeedType::Nearby) $posts->where('location', $location);
        if ($type === FeedType::Urgent) {
            $posts->whereIn('urgency', [
                PostUrgency::Important->value,
                PostUrgency::Urgent->value,
                PostUrgency::Critical->value,
            ]);
        }

        $candidateLimit = (int) config('recommendations.candidate_limit', 200);
        $postCandidates = $posts
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($candidateLimit)
            ->get();

        $campaignCandidates = collect();
        if ($type !== FeedType::Urgent) {
            $campaignQuery = Campaign::query()
                ->with(['organization.logoMedia', 'imageMedia', 'category', 'creator'])
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
                });
            if ($type === FeedType::Nearby) $campaignQuery->where('location', $location);
            $campaignCandidates = $campaignQuery
                ->orderByDesc('created_at')
                ->limit($candidateLimit)
                ->get();
        }

        $ranked = $this->rank($viewer, $postCandidates, $campaignCandidates);
        if ($type === FeedType::ForYou) {
            $ranked = $this->buildForYou($viewer, $ranked, $page, $perPage);
        } else {
            $ranked = $this->diversity->reorder($ranked)->map(function (array $item): array {
                $item['isExploration'] = false;
                $item['prompt'] = null;
                return $item;
            });
        }

        return $this->paginator($ranked, $page, $perPage);
    }

    private function rank(User $viewer, Collection $posts, Collection $campaigns): Collection
    {
        $preference = $viewer->preference()->first();
        $preferredCity = $preference?->preferred_city ?? $viewer->city;
        $preferredGovernorate = $preference?->preferred_governorate;
        $interests = UserCategoryInterest::query()->where('user_id', $viewer->id)->get()->keyBy('category_id');
        $follows = PublisherFollow::query()->where('follower_user_id', $viewer->id)->get();
        $followedUsers = $follows->where('target_type', PublisherFollow::TARGET_USER)->pluck('target_id')->flip();
        $followedOrganizations = $follows->where('target_type', PublisherFollow::TARGET_ORGANIZATION)->pluck('target_id')->flip();
        $excludedPostIds = PostFeedback::query()
            ->where('user_id', $viewer->id)
            ->whereIn('type', [PostFeedback::TYPE_NOT_INTERESTED, PostFeedback::TYPE_HIDE])
            ->pluck('post_id')->flip();
        $hiddenPublishers = HiddenPublisher::query()->where('user_id', $viewer->id)->get()
            ->mapWithKeys(fn (HiddenPublisher $hidden): array => ["{$hidden->publisher_type}:{$hidden->publisher_id}" => true]);
        $explorationRejectedSubjects = UserInteraction::query()
            ->where('user_id', $viewer->id)
            ->where('event_type', PersonalizationEventType::ExplorationNotInterested->value)
            ->get(['subject_type', 'subject_id'])
            ->mapWithKeys(fn ($interaction): array => ["{$interaction->subject_type}:{$interaction->subject_id}" => true]);
        $viewCounts = UserInteraction::query()
            ->where('user_id', $viewer->id)
            ->where('event_type', PersonalizationEventType::PostView->value)
            ->where('subject_type', 'post')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->selectRaw('subject_id, COUNT(*) as aggregate')
            ->groupBy('subject_id')
            ->pluck('aggregate', 'subject_id');
        $engagedPostIds = UserInteraction::query()
            ->where('user_id', $viewer->id)
            ->where('subject_type', 'post')
            ->whereIn('event_type', [
                PersonalizationEventType::PostOpen->value,
                PersonalizationEventType::PostLike->value,
                PersonalizationEventType::PostSave->value,
                PersonalizationEventType::HelpOffer->value,
                PersonalizationEventType::ContactAction->value,
            ])
            ->where('occurred_at', '>=', now()->subDays(30))
            ->pluck('subject_id')->flip();
        $capabilityIds = $viewer->capabilities()->pluck('capabilities.id')->map(fn ($id) => (string) $id)->flip();
        $groupCategories = GroupMember::query()
            ->where('user_id', $viewer->id)
            ->where('status', 'active')
            ->whereHas('group', fn ($query) => $query->where('status', 'active'))
            ->with('group:id,category')
            ->get()
            ->pluck('group.category')
            ->filter()
            ->map(fn ($category) => Str::lower(trim((string) $category)))
            ->unique()
            ->values();
        $explorationStates = UserExplorationCategoryState::query()
            ->where('user_id', $viewer->id)
            ->get()
            ->keyBy('category_id');

        $rankedPosts = $posts
            ->reject(fn (Post $post): bool =>
                $excludedPostIds->has((string) $post->id)
                || $hiddenPublishers->has($this->postPublisherKey($post))
                || $explorationRejectedSubjects->has('post:'.$post->id)
            )
            ->map(function (Post $post) use (
                $viewer, $preference, $preferredCity, $preferredGovernorate, $interests,
                $followedUsers, $followedOrganizations, $viewCounts, $engagedPostIds,
                $capabilityIds, $groupCategories, $explorationStates,
            ): array {
                $followsPublisher = $followedUsers->has((string) $post->author_id)
                    || ($post->organization_id !== null && $followedOrganizations->has((string) $post->organization_id));
                $interest = $interests->get($post->category_id);
                $scored = $this->scorePost(
                    $viewer,
                    $post,
                    $preference?->intent,
                    $preferredCity,
                    $preferredGovernorate,
                    $interest,
                    $followsPublisher,
                    (int) ($viewCounts[$post->id] ?? 0),
                    $engagedPostIds->has((string) $post->id),
                    $capabilityIds,
                    $groupCategories,
                    $preference?->availability_status,
                );
                $exploration = $this->explorationState(
                    $interest,
                    $followsPublisher,
                    $post->category_id,
                    $explorationStates->get($post->category_id),
                );

                return [
                    'contentType' => 'post',
                    'sortAt' => $post->published_at ?? $post->created_at,
                    'model' => $post,
                    'score' => $scored['score'],
                    'reasons' => $scored['reasons'],
                    'components' => $scored['components'],
                    'categoryId' => $post->category_id !== null ? (string) $post->category_id : null,
                    'categoryName' => $post->category?->name,
                    'publisherKey' => $this->postPublisherKey($post),
                    ...$exploration,
                ];
            });

        $rankedCampaigns = $campaigns
            ->reject(fn (Campaign $campaign): bool =>
                $hiddenPublishers->has('organization:'.$campaign->organization_id)
                || $explorationRejectedSubjects->has('campaign:'.$campaign->id)
            )
            ->map(function (Campaign $campaign) use (
                $preference, $preferredCity, $preferredGovernorate, $interests,
                $followedOrganizations, $groupCategories, $explorationStates,
            ): array {
                $followsPublisher = $followedOrganizations->has((string) $campaign->organization_id);
                $interest = $interests->get($campaign->category_id);
                $scored = $this->scoreCampaign(
                    $campaign,
                    $preference?->intent,
                    $preferredCity,
                    $preferredGovernorate,
                    $interest,
                    $followsPublisher,
                    $groupCategories,
                );
                $exploration = $this->explorationState(
                    $interest,
                    $followsPublisher,
                    $campaign->category_id,
                    $explorationStates->get($campaign->category_id),
                );

                return [
                    'contentType' => 'campaign',
                    'sortAt' => $campaign->created_at,
                    'model' => $campaign,
                    'score' => $scored['score'],
                    'reasons' => $scored['reasons'],
                    'components' => $scored['components'],
                    'categoryId' => $campaign->category_id !== null ? (string) $campaign->category_id : null,
                    'categoryName' => $campaign->category?->name,
                    'publisherKey' => 'organization:'.$campaign->organization_id,
                    ...$exploration,
                ];
            });

        return $rankedPosts->concat($rankedCampaigns)->sort(function (array $left, array $right): int {
            $scoreComparison = $right['score'] <=> $left['score'];
            return $scoreComparison !== 0
                ? $scoreComparison
                : (($right['sortAt']?->getTimestamp() ?? 0) <=> ($left['sortAt']?->getTimestamp() ?? 0));
        })->values();
    }

    private function buildForYou(User $viewer, Collection $ranked, int $page, int $perPage): Collection
    {
        $normal = $this->diversity->reorder(
            $ranked->reject(fn (array $item): bool => (bool) ($item['isExplorationCandidate'] ?? false))->values()
        )->map(function (array $item): array {
            $item['isExploration'] = false;
            $item['prompt'] = null;
            return $item;
        });

        $exploration = $this->diversity->reorder(
            $ranked->filter(fn (array $item): bool =>
                (bool) ($item['isExplorationCandidate'] ?? false)
                && ! (bool) ($item['explorationSuppressed'] ?? false)
            )->values()
        )->map(function (array $item): array {
            $item['isExploration'] = true;
            $item['reasons'] = array_values(array_unique([
                'discovery',
                ...($item['reasons'] ?? []),
            ]));
            $item['reasons'] = array_slice($item['reasons'], 0, 3);
            $item['prompt'] = null;
            return $item;
        });

        $mixed = $this->mixExploration($normal, $exploration, $perPage);
        return $this->annotateCurrentPagePrompts($viewer, $mixed, $page, $perPage);
    }

    private function mixExploration(Collection $normal, Collection $exploration, int $perPage): Collection
    {
        $ratio = max(0.0, min(0.5, (float) config('recommendations.exploration.ratio', 0.15)));
        $minimum = max(0, (int) config('recommendations.exploration.minimum_per_page', 1));
        $maximum = max($minimum, (int) config('recommendations.exploration.maximum_per_page', 3));
        $explorationPerPage = min($maximum, max($minimum, (int) round($perPage * $ratio)));
        $explorationPerPage = min($explorationPerPage, max(0, $perPage - 1));
        $normalPerPage = max(1, $perPage - $explorationPerPage);

        $normal = $normal->values();
        $exploration = $exploration->values();
        $result = collect();

        while ($normal->isNotEmpty() || $exploration->isNotEmpty()) {
            $normalChunk = $normal->take($normalPerPage)->values();
            $normal = $normal->slice($normalChunk->count())->values();

            $explorationCount = $normalChunk->isEmpty()
                ? min($explorationPerPage, $exploration->count())
                : min($explorationPerPage, $exploration->count());
            $explorationChunk = $exploration->take($explorationCount)->values();
            $exploration = $exploration->slice($explorationChunk->count())->values();

            if ($normalChunk->isEmpty() && $explorationChunk->isEmpty()) break;

            $chunk = $normalChunk;
            if ($explorationChunk->isNotEmpty()) {
                $baseSpacing = max(
                    (int) config('recommendations.exploration.minimum_normal_spacing', 4),
                    (int) floor($perPage / ($explorationChunk->count() + 1)),
                );

                foreach ($explorationChunk as $index => $item) {
                    $position = min($chunk->count(), max(2, ($index + 1) * $baseSpacing));
                    $chunk->splice($position, 0, [$item]);
                }
            }

            $result = $result->concat($chunk->take($perPage));

            if ($normalChunk->isEmpty()) {
                // Never turn an empty personalized pool into an exploration-only infinite feed.
                break;
            }
        }

        return $result->values();
    }

    private function annotateCurrentPagePrompts(User $viewer, Collection $items, int $page, int $perPage): Collection
    {
        $start = ($page - 1) * $perPage;
        $end = $start + $perPage - 1;
        $promptBudget = max(0, (int) config('recommendations.exploration.max_prompts_per_page', 2));
        $cooldownDays = max(1, (int) config('recommendations.exploration.prompt_cooldown_days', 30));

        return $items->map(function (array $item, int $index) use ($viewer, $start, $end, &$promptBudget, $cooldownDays): array {
            if ($index < $start || $index > $end || ! ($item['isExploration'] ?? false) || $promptBudget <= 0) {
                return $item;
            }

            $categoryId = $item['categoryId'] ?? null;
            $category = $item['model']?->category;
            if ($categoryId === null || $category === null) return $item;

            $state = UserExplorationCategoryState::query()->firstOrNew([
                'user_id' => $viewer->id,
                'category_id' => $categoryId,
            ]);
            $cutoff = now()->subDays($cooldownDays);
            if (($state->last_prompted_at !== null && $state->last_prompted_at->gte($cutoff))
                || ($state->last_responded_at !== null && $state->last_responded_at->gte($cutoff))) {
                return $item;
            }

            $state->last_prompted_at = now();
            $state->prompt_count = ((int) $state->prompt_count) + 1;
            $state->save();
            $promptBudget--;

            $item['prompt'] = [
                'shouldAsk' => true,
                'category' => [
                    'id' => (string) $category->id,
                    'name' => (string) $category->name,
                ],
            ];

            $this->interactions->recordCategoryEvent(
                $viewer,
                PersonalizationEventType::ExplorationPromptShown,
                $category,
                (string) $item['contentType'],
                (string) $item['model']->getKey(),
            );

            return $item;
        })->values();
    }

    private function scorePost(
        User $viewer,
        Post $post,
        ?UserIntent $intent,
        ?string $preferredCity,
        ?string $preferredGovernorate,
        ?UserCategoryInterest $interest,
        bool $followsPublisher,
        int $viewCount,
        bool $hasMeaningfulEngagement,
        Collection $capabilityIds,
        Collection $groupCategories,
        ?AvailabilityStatus $availability,
    ): array {
        $weights = config('recommendations.weights');
        $components = [];
        if ($followsPublisher) $components['followed_publisher'] = (float) $weights['followed_publisher'];
        $this->applyInterestComponents($components, $interest, $weights);

        if ($this->sameLocation($preferredCity, $post->location)) {
            $components['same_city'] = (float) $weights['same_city'];
        } elseif ($this->sameLocation($preferredGovernorate, $post->location)) {
            $components['same_governorate'] = (float) $weights['same_governorate'];
        }

        if ($this->postIntentMatches($intent, $post)) $components['intent_match'] = (float) $weights['intent_match'];

        $requiredIds = $post->requiredCapabilities->pluck('id')->map(fn ($id) => (string) $id);
        if ($requiredIds->contains(fn ($id) => $capabilityIds->has($id))) {
            $components['capability_match'] = (float) $weights['capability_match'];
        }

        if ($this->availabilityMatches($availability, $preferredCity, $post)) {
            $components['availability_match'] = (float) $weights['availability_match'];
        }
        if ($post->category !== null && $groupCategories->contains(Str::lower(trim((string) $post->category->name)))) {
            $components['group_affinity'] = (float) $weights['group_affinity'];
        }

        $freshness = $this->freshnessScore($post->published_at ?? $post->created_at);
        if ($freshness > 0) $components['fresh'] = $freshness;
        $urgency = $this->urgencyScore($post);
        if ($urgency > 0) $components['urgent'] = $urgency;
        $popularity = min(
            (float) config('recommendations.popularity_cap', 10),
            floor(((int) $post->views_count + (int) $post->reactions_count) / 10),
        );
        if ($popularity > 0) $components['popular_near_you'] = $popularity;
        if ($viewCount >= 3 && ! $hasMeaningfulEngagement) {
            $components['repeated_unengaged_view'] = (float) $weights['repeated_unengaged_view'];
        }

        return $this->scoreResult($components);
    }

    private function scoreCampaign(
        Campaign $campaign,
        ?UserIntent $intent,
        ?string $preferredCity,
        ?string $preferredGovernorate,
        ?UserCategoryInterest $interest,
        bool $followsOrganization,
        Collection $groupCategories,
    ): array {
        $weights = config('recommendations.weights');
        $components = [];
        if ($followsOrganization) $components['followed_publisher'] = (float) $weights['followed_publisher'];
        $this->applyInterestComponents($components, $interest, $weights);

        if ($this->sameLocation($preferredCity, $campaign->location)) {
            $components['same_city'] = (float) $weights['same_city'];
        } elseif ($this->sameLocation($preferredGovernorate, $campaign->location)) {
            $components['same_governorate'] = (float) $weights['same_governorate'];
        }

        if ($intent === null || $intent === UserIntent::Both || $intent === UserIntent::Giver) {
            $components['intent_match'] = (float) $weights['intent_match'];
        }
        if ($campaign->category !== null && $groupCategories->contains(Str::lower(trim((string) $campaign->category->name)))) {
            $components['group_affinity'] = (float) $weights['group_affinity'];
        }

        $freshness = $this->freshnessScore($campaign->created_at);
        if ($freshness > 0) $components['fresh'] = $freshness;
        $popularity = min(
            (float) config('recommendations.popularity_cap', 10),
            floor(((int) $campaign->donors_count + (int) $campaign->applicants_count) / 2),
        );
        if ($popularity > 0) $components['popular_near_you'] = $popularity;

        return $this->scoreResult($components);
    }

    private function applyInterestComponents(array &$components, ?UserCategoryInterest $interest, array $weights): void
    {
        if (($interest?->explicit_weight ?? 0) > 0) $components['explicit_interest'] = (float) $weights['explicit_interest'];
        if (($interest?->behavioral_weight ?? 0) > 0) {
            $components['behavioral_interest'] = min(
                (float) $weights['behavioral_interest'],
                (float) $interest->behavioral_weight,
            );
        }
    }

    private function explorationState(
        ?UserCategoryInterest $interest,
        bool $followsPublisher,
        mixed $categoryId,
        ?UserExplorationCategoryState $state,
    ): array {
        if ($categoryId === null || $followsPublisher) {
            return ['isExplorationCandidate' => false, 'explorationSuppressed' => false];
        }

        $threshold = (float) config('recommendations.exploration.interest_threshold', 2);
        $negativeThreshold = (float) config('recommendations.exploration.negative_threshold', -20);
        $explicit = (float) ($interest?->explicit_weight ?? 0);
        $behavioral = (float) ($interest?->behavioral_weight ?? 0);
        $candidate = $explicit <= 0 && $behavioral <= $threshold;

        $cooldownDays = max(1, (int) config('recommendations.exploration.prompt_cooldown_days', 30));
        $recentNegative = $state?->last_response === 'not_interested'
            && $state->last_responded_at !== null
            && $state->last_responded_at->gte(now()->subDays($cooldownDays));

        return [
            'isExplorationCandidate' => $candidate,
            'explorationSuppressed' => $behavioral <= $negativeThreshold || $recentNegative,
        ];
    }

    private function scoreResult(array $components): array
    {
        $positive = collect($components)->filter(fn (float $value): bool => $value > 0)->sortDesc();
        return [
            'score' => array_sum($components),
            'reasons' => $positive->keys()->take(3)->values()->all(),
            'components' => $components,
        ];
    }

    private function postIntentMatches(?UserIntent $intent, Post $post): bool
    {
        if ($intent === null || $intent === UserIntent::Both) return true;
        return match ($intent) {
            UserIntent::Giver => in_array($post->type, ['help_request', 'volunteer_opportunity', 'donation_campaign'], true),
            UserIntent::Receiver => in_array($post->type, ['service_offer', 'awareness', 'campaign_update'], true),
            UserIntent::Both => true,
        };
    }

    private function availabilityMatches(?AvailabilityStatus $availability, ?string $preferredCity, Post $post): bool
    {
        if ($post->type !== 'help_request' || $availability === null || $availability === AvailabilityStatus::Busy) return false;
        if (blank($post->location)) return true;
        return $this->sameLocation($preferredCity, $post->location);
    }

    private function freshnessScore(mixed $publishedAt): float
    {
        if ($publishedAt === null) return 0;
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
        return ! blank($left) && ! blank($right) && Str::lower(trim($left)) === Str::lower(trim($right));
    }

    private function postPublisherKey(Post $post): string
    {
        return $post->organization_id !== null
            ? 'organization:'.$post->organization_id
            : 'user:'.$post->author_id;
    }

    private function paginator(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
