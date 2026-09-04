<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\PersonalizationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\RecommendationFeedbackRequest;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\Mobile\InteractionTrackingService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class GenericRecommendationFeedbackController extends Controller
{
    public function __construct(private readonly InteractionTrackingService $tracking) {}

    public function __invoke(RecommendationFeedbackRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();
        $model = $this->resolveContent($data['contentType'], $data['contentId']);

        if (! $model) {
            return MobileApiResponse::error('not_found', 'The requested recommendation content was not found.', null, 404);
        }

        if ($model instanceof Post) {
            if ($data['action'] === 'interested') {
                $this->tracking->markInterested($user, $model);
            } else {
                $this->tracking->markNotInterested($user, $model);
            }
        } else {
            UserInteraction::query()->create([
                'user_id' => $user->id,
                'event_type' => $data['action'] === 'interested'
                    ? PersonalizationEventType::Interested->value
                    : PersonalizationEventType::NotInterested->value,
                'subject_type' => $data['contentType'],
                'subject_id' => $data['contentId'],
                'occurred_at' => now(),
            ]);
        }

        return MobileApiResponse::success([
            'contentType' => $data['contentType'],
            'contentId' => $data['contentId'],
            'action' => $data['action'],
            'saved' => true,
        ], 'Recommendation feedback saved successfully.');
    }

    private function resolveContent(string $type, string $id): ?Model
    {
        return match ($type) {
            'post' => Post::query()->find($id),
            'campaign' => Campaign::query()->find($id),
            'media' => Media::query()->find($id),
            'article' => Article::query()->find($id),
            default => null,
        };
    }
}
