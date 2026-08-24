<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Http\Resources\MediaUploadResource;
use App\Models\MediaUpload;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationVideoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrganizationVideoUploadController extends Controller
{
    public function __construct(private readonly OrganizationVideoUploadService $uploads) {}

    public function initiate(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);
        $data = $request->validate($this->initiationRules());
        $user = $request->user();

        $upload = $this->uploads->initiate(
            $organization,
            $user instanceof User ? $user : null,
            $data,
        );
        $upload->update(['description' => $data['description'] ?? null]);

        return MediaUploadResource::make($upload->refresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function status(Organization $organization, string $upload): MediaUploadResource
    {
        $this->authorize('view', $organization);

        return MediaUploadResource::make($this->upload($organization, $upload));
    }

    public function chunk(Request $request, Organization $organization, string $upload, int $chunk): MediaUploadResource
    {
        $this->authorize('update', $organization);

        return MediaUploadResource::make(
            $this->uploads->storeChunkStream(
                $this->upload($organization, $upload),
                $chunk,
                $request->getContent(true),
            ),
        );
    }

    public function pause(Organization $organization, string $upload): MediaUploadResource
    {
        $this->authorize('update', $organization);

        return MediaUploadResource::make(
            $this->uploads->pause($this->upload($organization, $upload)),
        );
    }

    public function resume(Organization $organization, string $upload): MediaUploadResource
    {
        $this->authorize('update', $organization);

        return MediaUploadResource::make(
            $this->uploads->resume($this->upload($organization, $upload)),
        );
    }

    public function complete(Organization $organization, string $upload): JsonResponse
    {
        $this->authorize('update', $organization);
        $result = $this->uploads->complete($this->upload($organization, $upload));
        $result['video']->update(['description' => $result['upload']->description]);

        return response()->json([
            'data' => [
                'upload' => MediaUploadResource::make($result['upload'])->resolve(),
                'video' => MediaResource::make($result['video']->refresh())->resolve(),
            ],
        ]);
    }

    public function cancel(Organization $organization, string $upload): Response
    {
        $this->authorize('update', $organization);
        $this->uploads->cancel($this->upload($organization, $upload));

        return response()->noContent();
    }

    private function upload(Organization $organization, string $upload): MediaUpload
    {
        return $this->uploads->findForOrganization($organization, $upload);
    }

    /** @return array<string, mixed> */
    private function initiationRules(): array
    {
        return [
            'originalName' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mimeType' => ['required', 'string', Rule::in(['video/mp4', 'video/quicktime', 'video/webm'])],
            'totalSize' => ['required', 'integer', 'min:1', 'max:'.OrganizationVideoUploadService::MAX_FILE_SIZE],
            'replaceVideoId' => ['nullable', 'uuid'],
        ];
    }
}
