<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\PostFeedback;
use App\Models\User;

class AdminUserPersonalizationService
{
    public function summary(User $user): array
    {
        $user->load(['preference', 'categoryInterests.category', 'capabilities']);
        $preference = $user->preference;
        $interests = $user->categoryInterests->filter(fn ($item) => $item->category !== null);
        return [
            'user' => ['id' => (string) $user->id, 'name' => (string) $user->name],
            'onboardingCompleted' => $preference?->onboarding_completed_at !== null,
            'onboardingCompletedAt' => $preference?->onboarding_completed_at?->toIso8601String(),
            'intent' => $preference?->intent?->value,
            'preferredCity' => $preference?->preferred_city,
            'preferredGovernorate' => $preference?->preferred_governorate,
            'preferredRadiusKm' => $preference?->preferred_radius_km,
            'remoteHelpEnabled' => (bool) ($preference?->remote_help_enabled ?? false),
            'availabilityStatus' => $preference?->availability_status?->value,
            'explicitInterests' => $interests->filter(fn ($item) => (float) $item->explicit_weight > 0)->sortByDesc('explicit_weight')->map(fn ($item) => ['category' => ['id' => (string) $item->category->id, 'name' => (string) $item->category->name], 'explicitWeight' => (float) $item->explicit_weight])->values()->all(),
            'topBehavioralInterests' => $interests->filter(fn ($item) => (float) $item->behavioral_weight > 0)->sortByDesc('behavioral_weight')->take(10)->map(fn ($item) => ['category' => ['id' => (string) $item->category->id, 'name' => (string) $item->category->name], 'behavioralWeight' => (float) $item->behavioral_weight])->values()->all(),
            'capabilities' => $user->capabilities->map(fn ($capability) => ['id' => (string) $capability->id, 'name' => (string) $capability->name, 'slug' => (string) $capability->slug, 'status' => (string) $capability->status])->values()->all(),
            'feedbackSummary' => [
                'notInterestedCount' => PostFeedback::query()->where('user_id', $user->id)->where('type', PostFeedback::TYPE_NOT_INTERESTED)->count(),
                'hiddenPostsCount' => PostFeedback::query()->where('user_id', $user->id)->where('type', PostFeedback::TYPE_HIDE)->count(),
                'hiddenPublishersCount' => $user->hiddenPublishers()->count(),
            ],
        ];
    }
}
