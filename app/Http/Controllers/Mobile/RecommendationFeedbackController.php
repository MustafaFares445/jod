<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\PostViewRequest;
use App\Models\Post;
use App\Models\User;
use App\Services\Mobile\InteractionTrackingService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationFeedbackController extends Controller
{
    public function __construct(private readonly InteractionTrackingService $tracking) {}

    public function view(PostViewRequest $request, Post $post): JsonResponse
    {
        $tracked = $this->tracking->recordPostView($this->user($request), $post->loadMissing('category'), $request->validated());

        return MobileApiResponse::success([
            'postId' => (string) $post->id,
            'tracked' => $tracked,
        ], 'Post view processed successfully.');
    }

    public function notInterested(Request $request, Post $post): JsonResponse
    {
        $this->tracking->markNotInterested($this->user($request), $post->loadMissing('category'));

        return MobileApiResponse::success([
            'postId' => (string) $post->id,
            'isNotInterested' => true,
        ], 'Recommendation feedback saved successfully.');
    }

    public function hidePost(Request $request, Post $post): JsonResponse
    {
        $this->tracking->hidePost($this->user($request), $post->loadMissing('category'));

        return MobileApiResponse::success([
            'postId' => (string) $post->id,
            'isHidden' => true,
        ], 'Post hidden successfully.');
    }

    public function hidePublisher(Request $request, string $targetType, string $targetId): JsonResponse
    {
        if (! in_array($targetType, ['user', 'organization'], true)) {
            return MobileApiResponse::error('validation_error', 'The publisher type is invalid.', [
                'targetType' => ['The target type must be user or organization.'],
            ]);
        }

        if (! $this->tracking->hidePublisher($this->user($request), $targetType, $targetId)) {
            return MobileApiResponse::error('not_found', 'The requested publisher could not be found.', null, 404);
        }

        return MobileApiResponse::success([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'isHidden' => true,
        ], 'Publisher hidden successfully.');
    }

    public function unhidePublisher(Request $request, string $targetType, string $targetId): JsonResponse
    {
        $this->tracking->unhidePublisher($this->user($request), $targetType, $targetId);

        return MobileApiResponse::success([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'isHidden' => false,
        ], 'Publisher visibility restored successfully.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
