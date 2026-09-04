<?php

declare(strict_types=1);

namespace App\Services;

class RecommendationSettingsService
{
    public const KEY = 'recommendations.settings';

    /** @var list<string> */
    private const ACTIVE_WEIGHT_KEYS = [
        'followed_publisher',
        'explicit_interest',
        'behavioral_interest',
        'same_city',
        'intent_match',
        'freshness',
        'urgency',
        'repeated_unengaged_view',
    ];

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    public function __construct(private readonly PlatformSettingService $platformSettings) {}

    /** @return list<string> */
    public function activeWeightKeys(): array
    {
        return self::ACTIVE_WEIGHT_KEYS;
    }

    /** @return array{weights: array<string, float>, candidateLimit: int, popularityCap: float, explorationRatio: float} */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $configWeights = (array) config('recommendations.weights', []);
        $defaultWeights = [];
        foreach (self::ACTIVE_WEIGHT_KEYS as $key) {
            $defaultWeights[$key] = (float) ($configWeights[$key] ?? 0);
        }

        $stored = $this->platformSettings->get(self::KEY, []);
        $stored = is_array($stored) ? $stored : [];
        $storedWeights = is_array($stored['weights'] ?? null) ? $stored['weights'] : [];
        $weights = $defaultWeights;
        foreach (self::ACTIVE_WEIGHT_KEYS as $key) {
            if (array_key_exists($key, $storedWeights)) {
                $weights[$key] = (float) $storedWeights[$key];
            }
        }

        $this->resolved = [
            'weights' => $weights,
            'candidateLimit' => (int) ($stored['candidateLimit'] ?? config('recommendations.candidate_limit', 200)),
            'popularityCap' => (float) ($stored['popularityCap'] ?? config('recommendations.popularity_cap', 10)),
            'explorationRatio' => (float) ($stored['explorationRatio'] ?? config('recommendations.exploration_ratio', 0.20)),
        ];

        return $this->resolved;
    }

    /** @param array<string, mixed> $changes */
    public function update(array $changes): array
    {
        $current = $this->all();
        $next = $current;

        if (isset($changes['weights']) && is_array($changes['weights'])) {
            foreach (self::ACTIVE_WEIGHT_KEYS as $key) {
                if (array_key_exists($key, $changes['weights'])) {
                    $next['weights'][$key] = (float) $changes['weights'][$key];
                }
            }
        }
        if (array_key_exists('candidateLimit', $changes)) {
            $next['candidateLimit'] = (int) $changes['candidateLimit'];
        }
        if (array_key_exists('popularityCap', $changes)) {
            $next['popularityCap'] = (float) $changes['popularityCap'];
        }
        if (array_key_exists('explorationRatio', $changes)) {
            $next['explorationRatio'] = (float) $changes['explorationRatio'];
        }

        $this->platformSettings->set(self::KEY, $next);
        $this->resolved = $next;

        return $next;
    }
}
