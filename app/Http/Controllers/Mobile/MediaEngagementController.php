<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\ReportPostRequest;
use App\Http\Resources\Mobile\MediaEngagementStateResource;
use App\Http\Resources\Mobile\MediaReportResource;
use App\Services\Mobile\MediaEngagementService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaEngagementController extends Controller
{
    public function __construct(private readonly MediaEngagementService $service) {}

    public function like(Request $request, string $media): JsonResponse
    {
        return $this->engagementResponse(
            fn () => $this->service->like($media, $request->user()),
            'Reel liked successfully.',
        );
    }

    public function unlike(Request $request, string $media): JsonResponse
    {
        return $this->engagementResponse(
            fn () => $this->service->unlike($media, $request->user()),
            'Reel unliked successfully.',
        );
    }

    public function save(Request $request, string $media): JsonResponse
    {
        return $this->engagementResponse(
            fn () => $this->service->save($media, $request->user()),
            'Reel saved successfully.',
        );
    }

    public function unsave(Request $request, string $media): JsonResponse
    {
        return $this->engagementResponse(
            fn () => $this->service->unsave($media, $request->user()),
            'Reel removed from saved items successfully.',
        );
    }

    public function report(ReportPostRequest $request, string $media): JsonResponse
    {
        try {
            $report = $this->service->report(
                $media,
                $request->user(),
                (string) $request->validated('reason'),
                $request->validated('details'),
            );
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested reel could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            MediaReportResource::make($report)->resolve($request),
            'Report submitted successfully.',
        );
    }

    /** @param callable(): array<string, mixed> $callback */
    private function engagementResponse(callable $callback, string $message): JsonResponse
    {
        try {
            $state = $callback();
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested reel could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            MediaEngagementStateResource::make($state)->resolve(),
            $message,
        );
    }
}
