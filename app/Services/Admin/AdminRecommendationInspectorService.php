<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\FeedType;
use App\Models\User;
use App\Services\Mobile\PersonalizedFeedService;

class AdminRecommendationInspectorService
{
    public function __construct(
        private readonly PersonalizedFeedService $feed,
        private readonly AdminUserPersonalizationService $personalization,
    ) {}

    public function preview(User $user, int $limit = 20): array
    {
        $paginator = $this->feed->paginate($user, FeedType::ForYou, 1, max(1, min($limit, 50)));

        return [
            'profile' => $this->personalization->summary($user),
            'recommendations' => collect($paginator->items())->map(function (array $item): array {
                $model = $item['model'];

                return [
                    'contentType' => $item['contentType'],
                    'contentId' => (string) $model->getKey(),
                    'title' => (string) ($model->title ?? $model->description ?? ''),
                    'score' => (float) ($item['score'] ?? 0),
                    'reasons' => $item['reasons'] ?? [],
                    'components' => $item['components'] ?? [],
                    'isExploration' => (bool) ($item['isExploration'] ?? false),
                ];
            })->values()->all(),
        ];
    }
}
