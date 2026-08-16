<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\ReportPostRequest;
use App\Http\Resources\Mobile\PostReportResource;
use App\Services\Mobile\PostEngagementService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class PostReportController extends Controller
{
    public function __construct(private readonly PostEngagementService $service) {}

    /**
     * Report a public active post.
     *
     * Requires a Sanctum bearer token. Every valid submission creates a new moderation report.
     *
     * @urlParam post string required The post identifier.
     *
     * @response array{success: bool, message: string, data: array{id: string, postId: string|null, status: string}, error: null, meta: array{}}
     */
    public function store(ReportPostRequest $request, string $post): JsonResponse
    {
        try {
            $report = $this->service->report($request->user(), $post, $request->validated());
        } catch (ModelNotFoundException) {
            return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            PostReportResource::make($report)->resolve($request),
            'Report submitted successfully.',
        );
    }
}
