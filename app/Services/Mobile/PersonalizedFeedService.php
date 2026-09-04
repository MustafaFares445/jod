<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\FeedType;
use App\Enums\HelpRequestStatus;
use App\Enums\PostUrgency;
use App\Enums\UserIntent;
use App\Models\Campaign;
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
        if ($type === FeedType::Urgent) $posts->whereIn('urgency', [PostUrgency::Important->value, PostUrgency::Urgent->value, PostUrgency::Critical->value]);

        $candidateLimit = (int) config('recommendations.candidate_limit', 200);
        $postCandidates = $posts->orderByDesc('published_at')->orderByDesc('created_at')->limit($candidateLimit)->get();

        $campaignCandidates = collect();
        if ($type !== FeedType::Urgent) {
            $campaignQuery = Campaign::query()
                ->with(['organization.logoMedia', 'imageMedia', 'category', 'creator'])
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
                });
            if ($type === FeedType::Nearby) $campaignQuery->where('location', $location);
            $campaignCandidates = $campaignQuery->orderByDesc('created_at')->limit($candidateLimit)->get();
        }

        return $this->paginator($this->rank($viewer, $postCandidates, $campaignCandidates), $page, $perPage);
    }

    private function rank(User $viewer, Collection $posts, Collection $campaigns): Collection
    {
        $preference = $viewer->preference()->first();
        $preferredCity = $preference?->preferred_city ?? $viewer->city;
        $interests = UserCategoryInterest::query()->where('user_id', $viewer->id)->get()->keyBy('category_id');
        $follows = PublisherFollow::query()->where('follower_user_id', $viewer->id)->get();
        $followedUsers = $follows->where('target_type', PublisherFollow::TARGET_USER)->pluck('target_id')->flip();
        $followedOrganizations = $follows->where('target_type', PublisherFollow::TARGET_ORGANIZATION)->pluck('target_id')->flip();
        $excludedPostIds = PostFeedback::query()->where('user_id', $viewer->id)->whereIn('type', [PostFeedback::TYPE_NOT_INTERESTED, PostFeedback::TYPE_HIDE])->pluck('post_id')->flip();
        $hiddenPublishers = HiddenPublisher::query()->where('user_id', $viewer->id)->get()->mapWithKeys(fn (HiddenPublisher $hidden): array => ["{$hidden->publisher_type}:{$hidden->publisher_id}" => true]);
        $viewCounts = UserInteraction::query()->where('user_id', $viewer->id)->where('event_type', 'post_view')->where('subject_type', 'post')->where('occurred_at', '>=', now()->subDays(30))->selectRaw('subject_id, COUNT(*) as aggregate')->groupBy('subject_id')->pluck('aggregate', 'subject_id');

        $rankedPosts = $posts
            ->reject(fn (Post $post): bool => $excludedPostIds->has((string) $post->id) || $hiddenPublishers->has($this->postPublisherKey($post)))
            ->map(function (Post $post) use ($viewer, $preference, $preferredCity, $interests, $followedUsers, $followedOrganizations, $viewCounts): array {
                $scored = $this->scorePost(
                    $viewer,
                    $post,
                    $preference?->intent,
                    $preferredCity,
                    $interests->get($post->category_id),
                    $followedUsers->has((string) $post->author_id),
                    $post->organization_id !== null && $followedOrganizations->has((string) $post->organization_id),
                    (int) ($viewCounts[$post->id] ?? 0),
                );
                return ['contentType' => 'post', 'sortAt' => $post->published_at ?? $post->created_at, 'model' => $post, 'score' => $scored['score'], 'reasons' => $scored['reasons'], 'components' => $scored['components']];
            });

        $rankedCampaigns = $campaigns
            ->reject(fn (Campaign $campaign): bool => $hiddenPublishers->has('organization:'.$campaign->organization_id))
            ->map(function (Campaign $campaign) use ($preference, $preferredCity, $interests, $followedOrganizations): array {
                $scored = $this->scoreCampaign(
                    $campaign,
                    $preference?->intent,
                    $preferredCity,
                    $interests->get($campaign->category_id),
                    $followedOrganizations->has((string) $campaign->organization_id),
                );
                return ['contentType' => 'campaign', 'sortAt' => $campaign->created_at, 'model' => $campaign, 'score' => $scored['score'], 'reasons' => $scored['reasons'], 'components' => $scored['components']];
            });

        return $rankedPosts->concat($rankedCampaigns)->sort(function (array $left, array $right): int {
            $scoreComparison = $right['score'] <=> $left['score'];
            return $scoreComparison !== 0 ? $scoreComparison : (($right['sortAt']?->getTimestamp() ?? 0) <=> ($left['sortAt']?->getTimestamp() ?? 0));
        })->values();
    }

    private function scorePost(User $viewer, Post $post, ?UserIntent $intent, ?string $preferredCity, ?UserCategoryInterest $interest, bool $followsAuthor, bool $followsOrganization, int $viewCount): array
    {
        $weights = config('recommendations.weights');
        $components = [];
        if ($followsAuthor || $followsOrganization) $components['followed_publisher'] = (float) $weights['followed_publisher'];
        $this->applyInterestComponents($components, $interest, $weights);
        if ($this->sameLocation($preferredCity, $post->location)) $components['same_city'] = (float) $weights['same_city'];
        if ($this->postIntentMatches($intent, $post)) $components['intent_match'] = (float) $weights['intent_match'];

        $requiredIds = $post->requiredCapabilities->pluck('id');
        if ($requiredIds->isNotEmpty() && $viewer->capabilities()->whereIn('capabilities.id', $requiredIds)->exists()) {
            $components['capability_match'] = (float) $weights['capability_match'];
        }

        $freshness = $this->freshnessScore($post->published_at ?? $post->created_at); if ($freshness > 0) $components['fresh'] = $freshness;
        $urgency = $this->urgencyScore($post); if ($urgency > 0) $components['urgent'] = $urgency;
        $popularity = min((float) config('recommendations.popularity_cap', 10), floor(((int) $post->views_count + (int) $post->reactions_count) / 10)); if ($popularity > 0) $components['popular_near_you'] = $popularity;
        if ($viewCount >= 3) $components['repeated_unengaged_view'] = (float) $weights['repeated_unengaged_view'];
        return $this->scoreResult($components);
    }

    private function scoreCampaign(Campaign $campaign, ?UserIntent $intent, ?string $preferredCity, ?UserCategoryInterest $interest, bool $followsOrganization): array
    {
        $weights = config('recommendations.weights');
        $components = [];
        if ($followsOrganization) $components['followed_publisher'] = (float) $weights['followed_publisher'];
        $this->applyInterestComponents($components, $interest, $weights);
        if ($this->sameLocation($preferredCity, $campaign->location)) $components['same_city'] = (float) $weights['same_city'];
        if ($intent === null || $intent === UserIntent::Both || $intent === UserIntent::Giver) $components['intent_match'] = (float) $weights['intent_match'];
        $freshness = $this->freshnessScore($campaign->created_at); if ($freshness > 0) $components['fresh'] = $freshness;
        $popularity = min((float) config('recommendations.popularity_cap', 10), floor(((int) $campaign->donors_count + (int) $campaign->applicants_count) / 2));
        if ($popularity > 0) $components['popular_near_you'] = $popularity;
        return $this->scoreResult($components);
    }

    private function applyInterestComponents(array &$components, ?UserCategoryInterest $interest, array $weights): void
    {
        if ($interest?->explicit_weight > 0) $components['explicit_interest'] = (float) $weights['explicit_interest'];
        if (($interest?->behavioral_weight ?? 0) > 0) $components['behavioral_interest'] = min((float) $weights['behavioral_interest'], (float) $interest->behavioral_weight);
    }

    private function scoreResult(array $components): array
    {
        $positive = collect($components)->filter(fn (float $value): bool => $value > 0)->sortDesc();
        return ['score' => array_sum($components), 'reasons' => $positive->keys()->take(3)->values()->all(), 'components' => $components];
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

    private function freshnessScore(mixed $publishedAt): float
    {
        if ($publishedAt === null) return 0;
        $hours = $publishedAt->diffInHours(now());
        return match (true) { $hours <= 6 => 10, $hours <= 24 => 8, $hours <= 72 => 5, $hours <= 168 => 2, default => 0 };
    }

    private function urgencyScore(Post $post): float
    {
        $urgency = $post->urgency?->value ?? $post->urgency ?? PostUrgency::Normal->value;
        return match ($urgency) { PostUrgency::Important->value => 4, PostUrgency::Urgent->value => 8, PostUrgency::Critical->value => 10, default => 0 };
    }

    private function sameLocation(?string $left, ?string $right): bool
    {
        return ! blank($left) && ! blank($right) && Str::lower(trim($left)) === Str::lower(trim($right));
    }

    private function postPublisherKey(Post $post): string
    {
        return $post->organization_id !== null ? 'organization:'.$post->organization_id : 'user:'.$post->author_id;
    }

    private function paginator(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items->slice(($page - 1) * $perPage, $perPage)->values(), $items->count(), $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }
}
