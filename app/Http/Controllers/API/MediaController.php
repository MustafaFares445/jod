<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $service) {}

    public function upload(MediaUploadRequest $request, string $model, string $modelId, string $prop): JsonResponse
    {
        $mediaModel = MediaModel::from($model);
        $target = $this->service->resolveTarget($mediaModel, $modelId);
        $this->authorizeTarget($mediaModel, $target);

        $media = $this->service->upload(
            $mediaModel,
            $modelId,
            $prop,
            $request->file('file'),
        );

        return MediaResource::make($media)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function replace(
        MediaUploadRequest $request,
        string $model,
        string $modelId,
        string $prop,
        string $media,
    ): MediaResource {
        $mediaModel = MediaModel::from($model);
        $target = $this->service->resolveTarget($mediaModel, $modelId);
        $this->authorizeTarget($mediaModel, $target);

        return MediaResource::make($this->service->replace(
            $mediaModel,
            $modelId,
            $prop,
            $media,
            $request->file('file'),
        ));
    }

    public function destroy(string $model, string $modelId, string $prop, string $media): Response
    {
        $mediaModel = MediaModel::from($model);
        $target = $this->service->resolveTarget($mediaModel, $modelId);
        $this->authorizeTarget($mediaModel, $target);
        $this->service->delete($mediaModel, $modelId, $prop, $media);

        return response()->noContent();
    }

    private function authorizeTarget(MediaModel $model, Model $target): void
    {
        match ($model) {
            MediaModel::ORGANIZATION => $this->authorize('updateSettings', $target instanceof Organization ? $target : Organization::class),
            MediaModel::CAMPAIGN => $this->authorize('updateOrganization', $target instanceof Campaign ? $target : Campaign::class),
            MediaModel::POST => $this->authorize('updateOrganization', $target instanceof Post ? $target : Post::class),
        };
    }
}
