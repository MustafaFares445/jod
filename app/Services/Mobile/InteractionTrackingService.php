<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\PersonalizationEventType;
use App\Models\Category;
use App\Models\HiddenPublisher;
use App\Models\Organization;
use App\Models\Post;
use App\Models\PostFeedback;
use App\Models\User;
use App\Models\UserInteraction;

class InteractionTrackingService
{
    public function __construct(private readonly UserInterestService $interests) {}

    /** @param array{durationSeconds: int, visiblePercent: int} $data */
    public function recordPostView(User $user, Post $post, array $data): bool
    {
        if ($data['durationSeconds'] < (int) config('recommendations.minimum_view_seconds', 2)) {
            return false;
        }

        $dedupeMinutes = (int) config('recommendations.view_dedupe_minutes', 30);
        $exists = UserInteraction::query()
            ->where('user_id', $user->id)
            ->where('event_type', PersonalizationEventType::PostView->value)
            ->where('subject_type', 'post')
            ->where('subject_id', $post->id)
            ->where('occurred_at', '>=', now()->subMinutes($dedupeMinutes))
            ->exists();

        if ($exists) {
            return false;
        }

        $this->record(
            $user,
            PersonalizationEventType::PostView,
            $post,
            $data,
        );

        return true;
    }

    public function markNotInterested(User $user, Post $post): void
    {
        $feedback = PostFeedback::query()->firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'type' => PostFeedback::TYPE_NOT_INTERESTED,
        ]);

        if ($feedback->wasRecentlyCreated) {
            $this->record($user, PersonalizationEventType::NotInterested, $post);
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
            $this->record($user, PersonalizationEventType::HidePost, $post);
        }
    }

    public function hidePublisher(User $user, string $publisherType, string $publisherId): bool
    {
        if (! $this->publisherExists($publisherType, $publisherId)) {
            return false;
        }

        HiddenPublisher::query()->firstOrCreate([
            'user_id' => $user->id,
            'publisher_type' => $publisherType,
            'publisher_id' => $publisherId,
        ]);

        UserInteraction::query()->create([
            'user_id' => $user->id,
            'event_type' => PersonalizationEventType::HidePublisher->value,
            'subject_type' => 'publisher',
            'subject_id' => $publisherId,
            'publisher_type' => $publisherType,
            'publisher_id' => $publisherId,
            'occurred_at' => now(),
        ]);

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
    private function record(User $user, PersonalizationEventType $eventType, Post $post, array $metadata = []): void
    {
        $publisherType = $post->organization_id !== null ? 'organization' : 'user';
        $publisherId = $post->organization_id ?? $post->author_id;

        UserInteraction::query()->create([
            'user_id' => $user->id,
            'event_type' => $eventType->value,
            'subject_type' => 'post',
            'subject_id' => $post->id,
            'category_id' => $post->category_id,
            'publisher_type' => $publisherType,
            'publisher_id' => $publisherId,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        if ($post->category_id === null) {
            return;
        }

        $category = $post->relationLoaded('category') ? $post->category : Category::query()->find($post->category_id);
        if ($category === null) {
            return;
        }

        $delta = (float) config("recommendations.interaction_weights.{$eventType->value}", 0);
        if ($delta !== 0.0) {
            $this->interests->adjustBehavioralWeight($user, $category, $delta);
        }
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
