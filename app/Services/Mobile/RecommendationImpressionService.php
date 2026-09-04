<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\FeedType;
use App\Enums\MediaModel;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Post;
use App\Models\RecommendationImpression;
use App\Models\User;
use Illuminate\Support\Str;

class RecommendationImpressionService
{
    public function record(User $viewer, FeedType $feedType, array $items): void
    {
        if ($items === []) return;
        $now = now();
        $rows = collect($items)->map(function (array $item) use ($viewer, $feedType, $now): ?array {
            $model = $item['model'] ?? null;
            if (! $model instanceof Post && ! $model instanceof Campaign && ! $model instanceof Media) return null;
            $categoryId = $model instanceof Post || $model instanceof Campaign ? $model->category_id : null;
            $publisherType = null;
            $publisherId = null;
            $city = null;
            if ($model instanceof Post) {
                $publisherType = $model->organization_id ? 'organization' : 'user';
                $publisherId = $model->organization_id ?: $model->author_id;
                $city = $model->location;
            } elseif ($model instanceof Campaign) {
                $publisherType = 'organization';
                $publisherId = $model->organization_id;
                $city = $model->location;
            } elseif ($model instanceof Media) {
                $modelType = $model->model_type instanceof MediaModel ? $model->model_type->value : (string) $model->model_type;
                $publisherType = $modelType === MediaModel::ORGANIZATION->value ? 'organization' : null;
                $publisherId = $publisherType ? $model->model_id : null;
            }
            return [
                'id' => (string) Str::uuid(),
                'user_id' => $viewer->id,
                'subject_type' => $item['contentType'] ?? strtolower(class_basename($model)),
                'subject_id' => $model->getKey(),
                'feed_type' => $feedType->value,
                'category_id' => $categoryId,
                'publisher_type' => $publisherType,
                'publisher_id' => $publisherId,
                'city' => $city,
                'score' => $item['score'] ?? null,
                'reasons' => json_encode($item['reasons'] ?? [], JSON_UNESCAPED_UNICODE),
                'is_exploration' => (bool) ($item['isExploration'] ?? false),
                'shown_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->filter()->values()->all();
        if ($rows !== []) RecommendationImpression::query()->insert($rows);
    }
}
