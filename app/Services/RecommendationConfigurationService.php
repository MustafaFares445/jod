<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class RecommendationConfigurationService
{
    private const SETTING_KEY = 'recommendation_overrides';
    private const CACHE_KEY = 'recommendations.effective-config';

    public function defaults(): array
    {
        $defaults = require config_path('recommendations.php');

        return is_array($defaults) ? $defaults : [];
    }

    public function overrides(): array
    {
        $value = PlatformSetting::get(self::SETTING_KEY, []);

        return is_array($value) ? $value : [];
    }

    public function effective(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            60,
            fn (): array => array_replace_recursive(
                $this->defaults(),
                $this->overrides(),
            ),
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->effective(), $key, $default);
    }

    public function update(array $data): array
    {
        $overrides = $this->overrides();

        if (array_key_exists('weights', $data)) {
            $overrides['weights'] = array_replace(
                (array) ($overrides['weights'] ?? []),
                (array) $data['weights'],
            );
        }
        if (array_key_exists('candidateLimit', $data)) {
            $overrides['candidate_limit'] = (int) $data['candidateLimit'];
        }
        if (array_key_exists('popularityCap', $data)) {
            $overrides['popularity_cap'] = (float) $data['popularityCap'];
        }

        PlatformSetting::set(self::SETTING_KEY, $overrides);
        Cache::forget(self::CACHE_KEY);

        return $this->effective();
    }

    public function reset(): array
    {
        PlatformSetting::query()->where('key', self::SETTING_KEY)->delete();
        Cache::forget(self::CACHE_KEY);

        return $this->effective();
    }
}
