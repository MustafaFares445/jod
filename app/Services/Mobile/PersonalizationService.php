<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\AvailabilityStatus;
use App\Enums\UserIntent;
use App\Models\Capability;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;

class PersonalizationService
{
    /** @return array<string, mixed> */
    public function options(): array
    {
        return [
            'intents' => collect(UserIntent::cases())->map(fn (UserIntent $intent): array => [
                'value' => $intent->value,
                'label' => match ($intent) {
                    UserIntent::Giver => 'أريد تقديم المساعدة',
                    UserIntent::Receiver => 'أبحث عن مساعدة',
                    UserIntent::Both => 'كلاهما',
                },
            ])->values()->all(),
            'categories' => Category::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->map(fn (Category $category): array => [
                    'id' => (string) $category->id,
                    'name' => (string) $category->name,
                    'description' => $category->description,
                ])->values()->all(),
            'capabilities' => Capability::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Capability $capability): array => $this->capabilityData($capability))
                ->values()->all(),
            'availabilityStatuses' => collect(AvailabilityStatus::cases())->map(fn (AvailabilityStatus $status): array => [
                'value' => $status->value,
                'label' => match ($status) {
                    AvailabilityStatus::Available => 'متاح',
                    AvailabilityStatus::Busy => 'مشغول حالياً',
                    AvailabilityStatus::Weekends => 'عطلة نهاية الأسبوع',
                    AvailabilityStatus::Evenings => 'مساءً',
                    AvailabilityStatus::RemoteOnly => 'عن بعد فقط',
                },
            ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function completeOnboarding(User $user, array $data): array
    {
        DB::transaction(function () use ($user, $data): void {
            UserPreference::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'intent' => $data['intent'],
                    'preferred_city' => $data['preferredCity'] ?? $user->city,
                    'preferred_governorate' => $data['preferredGovernorate'] ?? null,
                    'preferred_radius_km' => $data['preferredRadiusKm'] ?? null,
                    'remote_help_enabled' => (bool) ($data['remoteHelpEnabled'] ?? false),
                    'availability_status' => $data['availabilityStatus'] ?? null,
                    'onboarding_completed_at' => now(),
                ],
            );

            $this->syncInterests($user, $data['categoryIds']);
            $this->syncCapabilities($user, $data['capabilityIds'] ?? []);
        });

        return $this->profile($user->fresh());
    }

    /** @param array<string, mixed> $data */
    public function updatePreferences(User $user, array $data): array
    {
        $preference = UserPreference::query()->firstOrCreate(['user_id' => $user->id]);
        $mapping = [
            'intent' => 'intent',
            'preferredCity' => 'preferred_city',
            'preferredGovernorate' => 'preferred_governorate',
            'preferredRadiusKm' => 'preferred_radius_km',
            'remoteHelpEnabled' => 'remote_help_enabled',
            'availabilityStatus' => 'availability_status',
        ];

        foreach ($mapping as $requestKey => $attribute) {
            if (array_key_exists($requestKey, $data)) {
                $preference->setAttribute($attribute, $data[$requestKey]);
            }
        }

        $preference->save();

        return $this->profile($user->fresh());
    }

    /** @param array<int, string> $categoryIds */
    public function updateInterests(User $user, array $categoryIds): array
    {
        $this->syncInterests($user, $categoryIds);

        return $this->profile($user->fresh());
    }

    /** @param array<int, string> $capabilityIds */
    public function updateCapabilities(User $user, array $capabilityIds): array
    {
        $this->syncCapabilities($user, $capabilityIds);

        return $this->profile($user->fresh());
    }

    /** @return array<string, mixed> */
    public function profile(User $user): array
    {
        $user->load(['preference', 'categoryInterests.category', 'capabilities']);
        $preference = $user->preference;

        return [
            'onboardingCompleted' => $preference?->onboarding_completed_at !== null,
            'onboardingCompletedAt' => $preference?->onboarding_completed_at?->toIso8601String(),
            'intent' => $preference?->intent?->value,
            'preferredCity' => $preference?->preferred_city,
            'preferredGovernorate' => $preference?->preferred_governorate,
            'preferredRadiusKm' => $preference?->preferred_radius_km,
            'remoteHelpEnabled' => (bool) ($preference?->remote_help_enabled ?? false),
            'availabilityStatus' => $preference?->availability_status?->value,
            'interests' => $user->categoryInterests
                ->filter(fn (UserCategoryInterest $interest): bool => $interest->explicit_weight > 0 && $interest->category !== null)
                ->map(fn (UserCategoryInterest $interest): array => [
                    'category' => [
                        'id' => (string) $interest->category->id,
                        'name' => (string) $interest->category->name,
                    ],
                    'selectedByUser' => true,
                    'explicitWeight' => $interest->explicit_weight,
                    'behavioralWeight' => $interest->behavioral_weight,
                ])->values()->all(),
            'capabilities' => $user->capabilities
                ->map(fn (Capability $capability): array => $this->capabilityData($capability))
                ->values()->all(),
        ];
    }

    /** @param array<int, string> $categoryIds */
    private function syncInterests(User $user, array $categoryIds): void
    {
        $categoryIds = array_values(array_unique($categoryIds));
        $explicitWeight = (float) config('recommendations.interests.explicit_weight', 10);

        $query = UserCategoryInterest::query()->where('user_id', $user->id);
        if ($categoryIds === []) {
            $query->update(['explicit_weight' => 0]);
        } else {
            $query->whereNotIn('category_id', $categoryIds)->update(['explicit_weight' => 0]);
        }

        foreach ($categoryIds as $categoryId) {
            UserCategoryInterest::query()->updateOrCreate(
                ['user_id' => $user->id, 'category_id' => $categoryId],
                ['explicit_weight' => $explicitWeight],
            );
        }

        UserCategoryInterest::query()
            ->where('user_id', $user->id)
            ->where('explicit_weight', 0)
            ->where('behavioral_weight', 0)
            ->delete();
    }

    /** @param array<int, string> $capabilityIds */
    private function syncCapabilities(User $user, array $capabilityIds): void
    {
        $user->capabilities()->sync(array_values(array_unique($capabilityIds)));
    }

    /** @return array{id: string, name: string, slug: string} */
    private function capabilityData(Capability $capability): array
    {
        return [
            'id' => (string) $capability->id,
            'name' => (string) $capability->name,
            'slug' => (string) $capability->slug,
        ];
    }
}
