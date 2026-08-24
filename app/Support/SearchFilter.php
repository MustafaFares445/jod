<?php

declare(strict_types=1);

namespace App\Support;

final class SearchFilter
{
    /**
     * Resolve a search term from the canonical and legacy query parameter shapes.
     *
     * @param  array<string, mixed>  $params
     */
    public static function fromArray(array $params): string
    {
        foreach (['searchQueries', 'search', 'filter.search', 'filter_search'] as $key) {
            $value = self::value($params, $key);

            if (! is_scalar($value)) {
                continue;
            }

            $search = trim((string) $value);

            if ($search !== '' && $search !== 'all') {
                return $search;
            }
        }

        return '';
    }

    /** @param array<string, mixed> $params */
    private static function value(array $params, string $key): mixed
    {
        if (array_key_exists($key, $params)) {
            return $params[$key];
        }

        return data_get($params, $key);
    }
}
