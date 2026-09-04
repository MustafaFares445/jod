<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\PersonalizationEventType;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\HiddenPublisher;
use App\Models\Organization;
use App\Models\Post;
use App\Models\PostFeedback;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserInteraction;

class InteractionTrackingService
{
    public function __construct(private readonly UserInterestService $interests) {}

    /** @param array{durationSeconds: int, visiblePercent: int} $data */
    public function recordPostView(User $user, Post $post, array $data): bool
    {
        if ($data['durationSeconds'] < (int) config('recommendations.minimum_view_seconds', 2)) return false;
        if ($data['visiblePercent'] < (int) config('recommendations.minimum_visible_percent', 60)) return false;

        return $this->recordPostAction(
            $user,
            PersonalizationEventType::PostView,
            $post,
            $data,
            (int) config('recommendations.view_dedupe_minutes', 30),
        );
    }

    public function recordPostOpen(User $user, Post $post): bool
    {
        return $this->recordPostAction(
            $user,
            PersonalizationEventType::PostOpen,
            $post,
            [],
            (int) config('recommendations.open_dedupe_minutes', 30),
        );
    }

    /** @param array<string, mixed> $metadata */
    public function recordPostAction(
        User $user,
        PersonalizationEventType $eventType,
        Post $post,
        array $metadata = [],
        ?int $dedupeMinutes = null,
    ): bool {
        $publisherType = $post->organization_id !== null ? 'organization' : 'user';
        $publisherId = $post->organization_id ?? $post->author_id;

        return $this->recordEvent(
            $user,
            $eventType,
            'post',
            (string) $post->id,
            $post->category_id !== null ? (string) $post->category_id : null,
            $publisherType,
            $publisherId !== null ? (string) $publisherId : null,
            $metadata,
            $dedupeMinutes,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function recordCampaignAction(
        User $user,
        PersonalizationEventType $eventType,
        Campaign $campaign,
        array $metadata = [],
        ?int $dedupeMinutes = null,
    ): bool {
        return $this->recordEvent(
            $user,
            $eventType,
            'campaign',
            (string) $campaign->id,
            $campaign->category_id !== null ? (string) $campaign->category_id : null,
            'organization',
            (string) $campaign->organization_id,
            $metadata,
            $dedupeMinutes,
        );
    }

    public function recordSearch(User $user, string $query, ?Category $category): bool
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        if ($normalized === '') return false;

        return $this->recordEvent(
            $user,
            PersonalizationEventType::Search,
            'search',
            hash('sha256', mb_strtolower($normalized)),
            $category?->id !== null ? (string) $category->id : null,
            null,
            null,
            ['query' => $normalized],
            (int) config('recommendations.search_dedupe_minutes', 30),
        );
    }

    public function recordPublisherFollow(
        User $user,
        string $publisherType,
        string $publisherId,
        ?Category $category,
    ): bool {
        return $this->recordEvent(
            $user,
            PersonalizationEventType::PublisherFollow,
            'publisher',
            $publisherId,
            $category?->id !== null ? (string) $category->id : null,
            $publisherType,
            $publisherId,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function recordCategoryEvent(
        User $user,
        PersonalizationEventType $eventType,
        Category $category,
        string $subjectType,
        string $subjectId,
        array $metadata = [],
    ): UserCategoryInterest {
        $this->recordEvent(
            $user,
            $eventType,
            $subjectType,
            $subjectId,
            (string) $category->id,
            null,
            null,
            $metadata,
        );

        return UserCategoryInterest::query()
            ->where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->firstOrFail();
    }

    public function markNotInterested(User $user, Post $post): void
    {
        $feedback = PostFeedback::query()->firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'type' => PostFeedback::TYPE_NOT_INTERESTED,
        ]);

        if ($feedback->wasRecentlyCreated) {
            $this->recordPostAction($user, PersonalizationEventType::NotInterested, $post);
        }
    }

    public function hidePost(User $user, Post $post): void
    {
        $feedback = PostFeedback::query()->firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'type' => PostFeedback::TYPE_HIDE,
        ]);

        if ($feedback->wasRecentlyCreated) {
            $this->recordPostAction($user, PersonalizationEventType::HidePost, $post);
        }
    }

    public function hidePublisher(User $user, string $publisherType, string $publisherId): bool
    {
        if (! $this->publisherExists($publisherType, $publisherId)) return false;

        $hidden = HiddenPublisher::query()->firstOrCreate([
            'user_id' => $user->id,
            'publisher_type' => $publisherType,
            'publisher_id' => $publisherId,
        ]);

        if ($hidden->wasRecentlyCreated) {
            $this->recordEvent(
                $user,
                PersonalizationEventType::HidePublisher,
                'publisher',
                $publisherId,
                null,
                $publisherType,
                $publisherId,
            );
        }

        return true;
    }

    public function unhidePublisher(User $user, string $publisherType, string $publisherId): void
    {
        HiddenPublisher::query()
            ->where('user_id', $user->id)
            ->where('publisher_type', $publisherType)
            ->where('publisher_id', $publisherId)
            ->delete();
    }

    /** @param array<string, mixed> $metadata */
    private function recordEvent(
        User $user,
        PersonalizationEventType $eventType,
        string $subjectType,
        string $subjectId,
        ?string $categoryId,
        ?string $publisherType,
        ?string $publisherId,
        array $metadata = [],
        ?int $dedupeMinutes = null,
    ): bool {
        if ($dedupeMinutes !== null && $dedupeMinutes > 0) {
            $exists = UserInteraction::query()
                ->where('user_id', $user->id)
                ->where('event_type', $eventType->value)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->where('occurred_at', '>=', now()->subMinutes($dedupeMinutes))
                ->exists();
            if ($exists) return false;
        }

        UserInteraction::query()->create([
            'user_id' => $user->id,
            'event_type' => $eventType->value,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'category_id' => $categoryId,
            'publisher_type' => $publisherType,
            'publisher_id' => $publisherId,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        if ($categoryId === null) return true;
        $category = Category::query()->find($categoryId);
        if ($category === null) return true;

        $delta = (float) config("recommendations.interaction_weights.{$eventType->value}", 0);
        if ($delta !== 0.0) {
            $this->interests->adjustBehavioralWeight($user, $category, $delta);
        }

        return true;
    }

    private function publisherExists(string $publisherType, string $publisherId): bool
    {
        return match ($publisherType) {
            'user' => User::query()->whereKey($publisherId)->exists(),
            'organization' => Organization::query()->whereKey($publisherId)->exists(),
            default => false,
        };
    }
}
