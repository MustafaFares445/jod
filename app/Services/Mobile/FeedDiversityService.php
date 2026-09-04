<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use Illuminate\Support\Collection;

class FeedDiversityService
{
    /** @param Collection<int, array<string, mixed>> $items */
    public function reorder(Collection $items): Collection
    {
        $remaining = $items->values();
        $result = collect();
        $windowSize = max(1, (int) config('recommendations.diversity.window_size', 5));
        $maxPublisher = max(1, (int) config('recommendations.diversity.max_same_publisher', 2));
        $maxCategory = max(1, (int) config('recommendations.diversity.max_same_category', 3));

        while ($remaining->isNotEmpty()) {
            $window = $result->take(-$windowSize + 1);
            $index = $remaining->search(function (array $candidate) use ($window, $maxPublisher, $maxCategory): bool {
                $publisherKey = $candidate['publisherKey'] ?? null;
                $categoryId = $candidate['categoryId'] ?? null;

                $publisherCount = $publisherKey === null
                    ? 0
                    : $window->where('publisherKey', $publisherKey)->count();
                $categoryCount = $categoryId === null
                    ? 0
                    : $window->where('categoryId', $categoryId)->count();

                return $publisherCount < $maxPublisher && $categoryCount < $maxCategory;
            });

            if ($index === false) $index = 0;
            $selected = $remaining->get($index);
            if ($selected === null) break;

            $result->push($selected);
            $remaining->forget($index);
            $remaining = $remaining->values();
        }

        return $result->values();
    }
}
