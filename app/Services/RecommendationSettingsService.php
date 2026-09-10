<?php

declare(strict_types=1);

namespace App\Services;

class RecommendationSettingsService
{
    /** @var list<string> */
    private const ACTIVE_WEIGHT_KEYS = [
        'followed_publisher',
        'explicit_interest',
        'behavioral_interest',
        'same_city',
        'same_governorate',
        'intent_match',
        'capability_match',
        'availability_match',
        'freshness',
        'urgency',
        'group_affinity',
        'repeated_unengaged_view',
        'not_interested',
    ];

    /** @return list<string> */
    public function activeWeightKeys(): array
    {
        return self::ACTIVE_WEIGHT_KEYS;
    }

    /**
     * Recommendation configuration is source-controlled only.
     * No database/platform-setting overrides are read here.
     *
     * @return array{weights: array<string, float>, candidateLimit: int, popularityCap: float, explorationRatio: float}
     */
    public function all(): array
    {
        $configured = (array) config('recommendations.weights', []);
        $weights = [];

        foreach (self::ACTIVE_WEIGHT_KEYS as $key) {
            $weights[$key] = (float) ($configured[$key] ?? 0);
        }

        return [
            'weights' => $weights,
            'candidateLimit' => (int) config('recommendations.candidate_limit', 200),
            'popularityCap' => (float) config('recommendations.popularity_cap', 10),
            'explorationRatio' => (float) config('recommendations.exploration.ratio', config('recommendations.exploration_ratio', 0.15)),
        ];
    }
}
