<?php

declare(strict_types=1);

namespace App\Services;

class RecommendationConfigurationService
{
    public function defaults(): array
    {
        $defaults = require config_path('recommendations.php');

        return is_array($defaults) ? $defaults : [];
    }

    public function overrides(): array
    {
        return [];
    }

    public function effective(): array
    {
        return $this->defaults();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->defaults(), $key, $default);
    }

    /**
     * Runtime updates are intentionally disabled. Recommendation behavior is
     * changed only through source-controlled config and deployment.
     */
    public function update(array $data): array
    {
        return $this->defaults();
    }

    public function reset(): array
    {
        return $this->defaults();
    }
}
