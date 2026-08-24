<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Organization;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrganizationVideoController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function index(): AnonymousResourceCollection
    {
        $organization = $this->organization();
        $this->authorize('viewSettings', $organization);

        return MediaResource::collection(
            $this->mediaService->forTarget(MediaModel::ORGANIZATION, (string) $organization->id, 'videos'),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);
        $request->validate($this->videoRules());

        $video = $this->mediaService->upload(
            MediaModel::ORGANIZATION,
            (string) $organization->id,
            'videos',
            $request->file('file'),
        );

        return MediaResource::make($video)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $video): MediaResource
    {
        $organization = $this->organization();
        $this->authorize('viewSettings', $organization);

        return MediaResource::make($this->video($organization, $video));
    }

    public function update(Request $request, string $video): MediaResource
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);
        $request->validate($this->videoRules());
        $this->video($organization, $video);

        $updated = $this->mediaService->replace(
            MediaModel::ORGANIZATION,
            (string) $organization->id,
            'videos',
            $video,
            $request->file('file'),
        );

        return MediaResource::make($updated);
    }

    public function destroy(string $video): Response
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);
        $this->video($organization, $video);

        $this->mediaService->delete(
            MediaModel::ORGANIZATION,
            (string) $organization->id,
            'videos',
            $video,
        );

        return response()->noContent();
    }

    private function organization(): Organization
    {
        /** @var Organization|null $organization */
        $organization = auth()->user()?->organization;
        abort_if($organization === null, 404, 'Organization not found');

        return $organization;
    }

    private function video(Organization $organization, string $video): Media
    {
        return Media::query()
            ->whereKey($video)
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('model_id', $organization->id)
            ->where('prop', 'videos')
            ->firstOrFail();
    }

    /** @return array<string, array<int, string>> */
    private function videoRules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
        ];
    }
}
