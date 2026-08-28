<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Http\Resources\Mobile\MobileCampaignResource;
use App\Http\Resources\Mobile\MobileHomePostResource;
use App\Http\Resources\Mobile\MobilePublisherResource;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\Mobile\FollowingFeedService;
use App\Services\Mobile\FollowService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(
        private readonly FollowService $follows,
        private readonly FollowingFeedService $feed,
    ) {}

    public function follow(Request $request, string $targetType, string $targetId): JsonResponse
    {
        $user = $this->user($request);
        $this->follows->follow($user, $targetType, $targetId);

        return MobileApiResponse::success([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'isFollowing' => true,
            'followersCount' => $this->follows->followersCount($targetType, $targetId),
        ], 'Publisher followed successfully.');
    }

    public function unfollow(Request $request, string $targetType, string $targetId): JsonResponse
    {
        $user = $this->user($request);
        $this->follows->unfollow($user, $targetType, $targetId);

        return MobileApiResponse::success([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'isFollowing' => false,
            'followersCount' => $this->follows->followersCount($targetType, $targetId),
        ], 'Publisher unfollowed successfully.');
    }

    public function following(Request $request): JsonResponse
    {
        $paginator = $this->follows->following(
            $this->user($request),
            (string) $request->query('type', 'all'),
            (int) $request->query('perPage', 20),
        );

        return MobileApiResponse::paginated(
            $paginator->through(fn ($publisher) => MobilePublisherResource::make($publisher)->resolve($request)),
            'Following retrieved successfully.',
        );
    }

    public function feed(Request $request): JsonResponse
    {
        $paginator = $this->feed->paginate(
            $this->user($request),
            max(1, (int) $request->query('page', 1)),
            (int) $request->query('perPage', 20),
        );

        return MobileApiResponse::paginated(
            $paginator->through(function (array $item) use ($request): array {
                $model = $item['model'];
                $content = match (true) {
                    $model instanceof Post => MobileHomePostResource::make($model)->resolve($request),
                    $model instanceof Campaign => MobileCampaignResource::make($model)->resolve($request),
                    $model instanceof Media => MediaResource::make($model)->resolve($request),
                };

                return ['contentType' => $item['contentType'], 'publishedAt' => $item['sortAt']?->toIso8601String(), 'content' => $content];
            }),
            'Following feed retrieved successfully.',
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        return $user;
    }
}
