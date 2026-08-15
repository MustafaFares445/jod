<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\SavedPostRequest;
use App\Http\Resources\Mobile\SavedPostResource;
use App\Models\SavedPost;
use App\Services\Mobile\SavedPostService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;

class SavedPostController extends Controller
{
    public function __construct(private readonly SavedPostService $service) {}

    /**
     * List the authenticated user's saved public posts.
     *
     * Requires a Sanctum bearer token.
     *
     * @queryParam page int optional The page number.
     * @queryParam perPage int optional The number of items per page.
     *
     * @response array{success: bool, message: string, data: array<int, array{id: string, title: string|null, summary: string|null, type: string, status: string, organizationName: string|null, authorName: string|null, location: string|null, campaignTitle: string|null, submittedAt: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null, reviewedBy: string|null, rejectionReason: string|null, viewsCount: int, reactionsCount: int, applicationsCount: int, savedAt: string|null}>, error: null, meta: array{currentPage: int, perPage: int, total: int, lastPage: int}}
     */
    public function index(SavedPostRequest $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->user(), $request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn (SavedPost $savedPost) => SavedPostResource::make($savedPost)->resolve($request)),
            'Saved posts retrieved successfully.',
        );
    }
}
