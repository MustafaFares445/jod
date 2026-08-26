<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Article;
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
        $this->markPostUpdated($target);

        return MediaResource::make($media)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function replace(
        MediaUploadRequest $request,
        string $model,
        string $modelId,
        string $prop,
        string $mediaId,
    ): MediaResource {
        $mediaModel = MediaModel::from($model);
        $target = $this->service->resolveTarget($mediaModel, $modelId);
        $this->authorizeTarget($mediaModel, $target);

        $media = $this->service->replace(
            $mediaModel,
            $modelId,
            $prop,
            $mediaId,
            $request->file('file'),
        );
        $this->markPostUpdated($target);

        return MediaResource::make($media);
    }

    public function destroy(string $model, string $modelId, string $prop, string $mediaId): Response
    {
        $mediaModel = MediaModel::from($model);
        $target = $this->service->resolveTarget($mediaModel, $modelId);
        $this->authorizeTarget($mediaModel, $target);
        $this->service->delete($mediaModel, $modelId, $prop, $mediaId);
        $this->markPostUpdated($target);

        return response()->noContent();
    }

    private function authorizeTarget(MediaModel $model, Model $target): void
    {
        match ($model) {
            MediaModel::ORGANIZATION => $this->authorize('updateSettings', $target),
            MediaModel::CAMPAIGN => $this->authorize('updateOrganization', $target),
            MediaModel::POST => $this->authorizePost($target),
            MediaModel::ARTICLE => $target instanceof Article
                ? $this->authorize('update', $target)
                : abort(404),
        };
    }

    private function authorizePost(Model $target): void
    {
        if (! $target instanceof Post) {
            abort(404);
        }

        $target->loadMissing('author');

        if ($target->organization_id !== null) {
            $this->authorize('updateOrganization', $target);

            return;
        }

        if ($target->author?->user_type === 'admin') {
            $this->authorize('updateAdmin', $target);

            return;
        }

        $this->authorize('manageOwnMedia', $target);
    }

    private function markPostUpdated(Model $target): void
    {
        if ($target instanceof Post && auth()->id() !== null) {
            $target->update(['updated_by' => auth()->id()]);
        }
    }
}
