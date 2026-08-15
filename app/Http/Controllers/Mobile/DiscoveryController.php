<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\CampaignDiscoveryRequest;
use App\Http\Requests\Mobile\CategoryDiscoveryRequest;
use App\Http\Requests\Mobile\PostDiscoveryRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PostResource;
use App\Services\CampaignService;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function __construct(
        private readonly PostService $postService,
        private readonly CampaignService $campaignService,
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * List public posts for mobile discovery.
     *
     * Public endpoint.
     *
     * @queryParam page int optional The page number.
     * @queryParam perPage int optional The number of items per page.
     * @queryParam search string optional Free-text search across public post fields.
     * @queryParam status string optional Public status filter. Allowed value: published.
     * @queryParam type string optional Post type filter.
     * @queryParam location string optional Location filter.
     * @queryParam organizationId string optional Organization filter.
     * @queryParam sort string optional Sort order.
     * @queryParam sortBy string optional Legacy sort alias.
     *
     * @response array{success: bool, message: string, data: array<int, array{id: string, title: string, summary: string|null, type: string, status: string, organizationName: string|null, authorName: string|null, location: string|null, campaignTitle: string|null, images: list<string>, submittedAt: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null, reviewedBy: string|null, rejectionReason: string|null, viewsCount: int, reactionsCount: int, applicationsCount: int}>, error: null, meta: array{currentPage: int, perPage: int, total: int, lastPage: int, viewer?: array{isAuthenticated: bool, userId: string, organizationId: string|null}}}
     */
    public function posts(PostDiscoveryRequest $request): JsonResponse
    {
        $paginator = $this->postService->discover($request->validated());
        $meta = $this->viewerMeta($request);

        return MobileApiResponse::paginated(
            $paginator->through(fn ($post) => PostResource::make($post)->resolve($request)),
            'Posts retrieved successfully.',
            $meta,
        );
    }

    /**
     * Show a public post for mobile discovery.
     *
     * Public endpoint.
     *
     * @urlParam post string required The post identifier.
     *
     * @response array{success: bool, message: string, data: array{id: string, title: string, summary: string|null, type: string, status: string, organizationName: string|null, authorName: string|null, location: string|null, campaignTitle: string|null, images: list<string>, submittedAt: string|null, createdAt: string|null, updatedAt: string|null, publishedAt: string|null, reviewedBy: string|null, rejectionReason: string|null, viewsCount: int, reactionsCount: int, applicationsCount: int}, error: null, meta: array{viewer?: array{isAuthenticated: bool, userId: string, organizationId: string|null}}}
     */
    public function showPost(Request $request, string $post): JsonResponse
    {
        $model = $this->postService->findPublicPost($post);

        if (! $model) {
            return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            PostResource::make($model)->resolve($request),
            'Post retrieved successfully.',
            $this->viewerMeta($request),
        );
    }

    /**
     * List active campaigns for mobile discovery.
     *
     * Public endpoint.
     *
     * @queryParam page int optional The page number.
     * @queryParam perPage int optional The number of items per page.
     * @queryParam search string optional Free-text search across public campaign fields.
     * @queryParam status string optional Public status filter. Allowed value: active.
     * @queryParam category string optional Campaign category filter.
     * @queryParam location string optional Location filter.
     * @queryParam organizationId string optional Organization filter.
     * @queryParam sort string optional Sort order.
     * @queryParam sortBy string optional Legacy sort alias.
     *
     * @response array{success: bool, message: string, data: array<int, array{id: string, title: string, summary: string|null, category: string, status: string, organizationName: string|null, managerName: string|null, location: string|null, goalAmount: float, raisedAmount: float, beneficiariesCount: int, donorsCount: int, applicantsCount: int, startDate: string|null, endDate: string|null, submittedAt: string|null, createdAt: string|null, updatedAt: string|null, closedAt: string|null, closedReason: string|null, reviewedBy: string|null, rejectionReason: string|null}>, error: null, meta: array{currentPage: int, perPage: int, total: int, lastPage: int, viewer?: array{isAuthenticated: bool, userId: string, organizationId: string|null}}}
     */
    public function campaigns(CampaignDiscoveryRequest $request): JsonResponse
    {
        $paginator = $this->campaignService->discover($request->validated());
        $meta = $this->viewerMeta($request);

        return MobileApiResponse::paginated(
            $paginator->through(fn ($campaign) => CampaignResource::make($campaign)->resolve($request)),
            'Campaigns retrieved successfully.',
            $meta,
        );
    }

    /**
     * Show an active campaign for mobile discovery.
     *
     * Public endpoint.
     *
     * @urlParam campaign string required The campaign identifier.
     *
     * @response array{success: bool, message: string, data: array{id: string, title: string, summary: string|null, category: string, status: string, organizationName: string|null, managerName: string|null, location: string|null, goalAmount: float, raisedAmount: float, beneficiariesCount: int, donorsCount: int, applicantsCount: int, startDate: string|null, endDate: string|null, submittedAt: string|null, createdAt: string|null, updatedAt: string|null, closedAt: string|null, closedReason: string|null, reviewedBy: string|null, rejectionReason: string|null}, error: null, meta: array{viewer?: array{isAuthenticated: bool, userId: string, organizationId: string|null}}}
     */
    public function showCampaign(Request $request, string $campaign): JsonResponse
    {
        $model = $this->campaignService->findPublicCampaign($campaign);

        if (! $model) {
            return MobileApiResponse::error('not_found', 'The requested campaign could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            CampaignResource::make($model)->resolve($request),
            'Campaign retrieved successfully.',
            $this->viewerMeta($request),
        );
    }

    /**
     * List active categories for mobile discovery.
     *
     * Public endpoint.
     *
     * @queryParam page int optional The page number.
     * @queryParam perPage int optional The number of items per page.
     * @queryParam search string optional Free-text search across category fields.
     * @queryParam target string optional Category target filter.
     * @queryParam status string optional Public status filter. Allowed value: active.
     * @queryParam sort string optional Sort order.
     *
     * @response array{success: bool, message: string, data: array<int, array{id: string, name: string, target: string, description: string|null, usageCount: int, status: string, createdAt: string|null, updatedAt: string|null}>, error: null, meta: array{currentPage: int, perPage: int, total: int, lastPage: int, viewer?: array{isAuthenticated: bool, userId: string, organizationId: string|null}}}
     */
    public function categories(CategoryDiscoveryRequest $request): JsonResponse
    {
        $paginator = $this->categoryService->discover($request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn ($category) => CategoryResource::make($category)->resolve($request)),
            'Categories retrieved successfully.',
            $this->viewerMeta($request),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function viewerMeta(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        return [
            'viewer' => [
                'isAuthenticated' => true,
                'userId' => $user->id,
                'organizationId' => $user->organization_id,
            ],
        ];
    }
}
