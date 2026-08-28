<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

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
    public function __construct(
        private readonly OrganizationVideoUploadService $uploads,
        private readonly NotificationEventService $notifications,
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);
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

    public function status(string $upload): MediaUploadResource
    {
        $organization = $this->organization();
        $this->authorize('viewSettings', $organization);

        return MediaUploadResource::make($this->upload($organization, $upload));
    }

    public function chunk(Request $request, string $upload, int $chunk): MediaUploadResource
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);
        $session = $this->upload($organization, $upload);

        return MediaUploadResource::make(
            $this->uploads->storeChunkStream($session, $chunk, $request->getContent(true)),
        );
    }

    public function pause(string $upload): MediaUploadResource
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);

        return MediaUploadResource::make(
            $this->uploads->pause($this->upload($organization, $upload)),
        );
    }

    public function resume(string $upload): MediaUploadResource
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);

        return MediaUploadResource::make(
            $this->uploads->resume($this->upload($organization, $upload)),
        );
    }

    public function complete(string $upload): JsonResponse
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);
        $result = $this->uploads->complete($this->upload($organization, $upload));
        $result['video']->update(['description' => $result['upload']->description]);
        $creatorId = auth()->id() !== null ? (string) auth()->id() : null;
        $this->notifications->notifyPublisherFollowers(
            'organization',
            (string) $organization->id,
            NotificationEventType::MediaPublished,
            'فيديو جديد من منظمة تتابعها',
            "نشرت {$organization->name} فيديو جديدًا.",
            'system',
            'normal',
            $result['video']->original_name,
            '/media/'.$result['video']->id,
            (string) $organization->id,
            $creatorId,
        );

        return response()->json([
            'data' => [
                'upload' => MediaUploadResource::make($result['upload'])->resolve(),
                'video' => MediaResource::make($result['video']->refresh())->resolve(),
            ],
        ]);
    }

    public function cancel(string $upload): Response
    {
        $organization = $this->organization();
        $this->authorize('updateSettings', $organization);
        $this->uploads->cancel($this->upload($organization, $upload));

        return response()->noContent();
    }

    private function organization(): Organization
    {
        /** @var Organization|null $organization */
        $organization = auth()->user()?->organization;
        abort_if($organization === null, 404, 'Organization not found');

        return $organization;
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
