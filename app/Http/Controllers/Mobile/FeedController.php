<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\FeedType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\FeedRequest;
use App\Http\Resources\MediaResource;
use App\Http\Resources\Mobile\MobileCampaignResource;
use App\Http\Resources\Mobile\MobileHomePostResource;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\Mobile\FollowingFeedService;
use App\Services\Mobile\PersonalizedFeedService;
use App\Services\Mobile\RecommendationImpressionService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;

class FeedController extends Controller
{
    public function __construct(
        private readonly PersonalizedFeedService $personalizedFeed,
        private readonly FollowingFeedService $followingFeed,
        private readonly RecommendationImpressionService $impressions,
    ) {}

    public function __invoke(FeedRequest $request): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);

        $validated = $request->validated();
        $type = FeedType::from((string) ($validated['type'] ?? FeedType::ForYou->value));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['perPage'] ?? 20);

        $paginator = $type === FeedType::Following
            ? $this->followingFeed->paginate($viewer, $page, $perPage)
            : $this->personalizedFeed->paginate($viewer, $type, $page, $perPage);

        $this->impressions->record($viewer, $type, $paginator->items());

        return MobileApiResponse::paginated(
            $paginator->through(fn (array $item): array => $this->serializeItem($request, $item, $type)),
            'Personalized feed retrieved successfully.',
            ['feedType' => $type->value],
        );
    }

    /** @param array<string, mixed> $item */
    private function serializeItem(FeedRequest $request, array $item, FeedType $type): array
    {
        $model = $item['model'];
        $content = match (true) {
            $model instanceof Post => MobileHomePostResource::make($model)->resolve($request),
            $model instanceof Campaign => MobileCampaignResource::make($model)->resolve($request),
            $model instanceof Media => MediaResource::make($model)->resolve($request),
        };

        if ($model instanceof Post) {
            $content['urgency'] = $model->urgency?->value ?? $model->urgency ?? 'normal';
            $content['expiresAt'] = $model->expires_at?->toIso8601String();
        }

        return [
            'contentType' => $item['contentType'],
            'publishedAt' => $item['sortAt']?->toIso8601String(),
            'recommendation' => [
                'reasons' => $item['reasons'] ?? ($type === FeedType::Following ? ['followed_publisher'] : []),
            ],
            'content' => $content,
        ];
    }
}
