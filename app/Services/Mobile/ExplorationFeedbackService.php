<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\PersonalizationEventType;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostFeedback;
use App\Models\User;
use App\Models\UserCategoryInterest;
use App\Models\UserExplorationCategoryState;
use App\Models\UserInteraction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExplorationFeedbackService
{
    public function __construct(private readonly InteractionTrackingService $interactions) {}

    /** @param array{contentType:string,contentId:string,categoryId:string,response:string} $data */
    public function submit(User $user, array $data): array
    {
        [$content, $category] = $this->resolveContent($data);
        $event = $data['response'] === 'interested'
            ? PersonalizationEventType::ExplorationInterested
            : PersonalizationEventType::ExplorationNotInterested;
        $opposite = $data['response'] === 'interested'
            ? PersonalizationEventType::ExplorationNotInterested
            : PersonalizationEventType::ExplorationInterested;

        return DB::transaction(function () use ($user, $data, $content, $category, $event, $opposite): array {
            UserInteraction::query()
                ->where('user_id', $user->id)
                ->where('event_type', $opposite->value)
                ->where('subject_type', $data['contentType'])
                ->where('subject_id', $data['contentId'])
                ->delete();

            $alreadyRecorded = UserInteraction::query()
                ->where('user_id', $user->id)
                ->where('event_type', $event->value)
                ->where('subject_type', $data['contentType'])
                ->where('subject_id', $data['contentId'])
                ->exists();

            if (! $alreadyRecorded) {
                $this->interactions->recordCategoryEvent(
                    $user,
                    $event,
                    $category,
                    $data['contentType'],
                    $data['contentId'],
                    ['source' => 'exploration_prompt'],
                );
            }

            if ($content instanceof Post) {
                if ($data['response'] === 'not_interested') {
                    PostFeedback::query()->firstOrCreate([
                        'user_id' => $user->id,
                        'post_id' => $content->id,
                        'type' => PostFeedback::TYPE_NOT_INTERESTED,
                    ]);
                } else {
                    PostFeedback::query()
                        ->where('user_id', $user->id)
                        ->where('post_id', $content->id)
                        ->where('type', PostFeedback::TYPE_NOT_INTERESTED)
                        ->delete();
                }
            }

            UserExplorationCategoryState::query()->updateOrCreate(
                ['user_id' => $user->id, 'category_id' => $category->id],
                [
                    'last_response' => $data['response'],
                    'last_responded_at' => now(),
                ],
            );

            $interest = UserCategoryInterest::query()
                ->where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->first();

            return [
                'categoryId' => (string) $category->id,
                'response' => $data['response'],
                'behavioralWeight' => (float) ($interest?->behavioral_weight ?? 0),
                'feedRefreshRecommended' => true,
            ];
        });
    }

    /** @return array{Model,Category} */
    private function resolveContent(array $data): array
    {
        $content = match ($data['contentType']) {
            'post' => Post::query()
                ->whereKey($data['contentId'])
                ->where('status', 'published')
                ->first(),
            'campaign' => Campaign::query()
                ->whereKey($data['contentId'])
                ->where('status', 'active')
                ->first(),
            default => null,
        };

        if ($content === null || (string) $content->category_id !== (string) $data['categoryId']) {
            throw ValidationException::withMessages([
                'contentId' => ['The selected content does not belong to the supplied category.'],
            ]);
        }

        $category = Category::query()->whereKey($data['categoryId'])->where('status', 'active')->first();
        if ($category === null) {
            throw ValidationException::withMessages(['categoryId' => ['The selected category is not active.']]);
        }

        return [$content, $category];
    }
}
