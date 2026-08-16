<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\CampaignDiscoveryRequest;
use App\Http\Requests\Mobile\CategoryDiscoveryRequest;
use App\Http\Requests\Mobile\PostDiscoveryRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\Mobile\MobileHomePostResource;
use App\Models\User;
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
     * Public endpoint. A valid Sanctum bearer token is optional and enriches
     * viewer-specific fields such as saved and application state.
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
     */
    public function posts(PostDiscoveryRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $paginator = $this->postService->discover($request->validated(), $viewer);

        return MobileApiResponse::paginated(
            $paginator->through(fn ($post) => MobileHomePostResource::make($post)->resolve($request)),
            'Posts retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * Show a public post for mobile discovery.
     *
     * Public endpoint. A valid Sanctum bearer token is optional.
     *
     * @urlParam post string required The post identifier.
     */
    public function showPost(Request $request, string $post): JsonResponse
    {
        $viewer = $this->viewer($request);
        $model = $this->postService->findPublicPost($post, $viewer);

        if (! $model) {
            return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            MobileHomePostResource::make($model)->resolve($request),
            'Post retrieved successfully.',
            $this->viewerMeta($viewer),
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
     */
    public function campaigns(CampaignDiscoveryRequest $request): JsonResponse
    {
        $paginator = $this->campaignService->discover($request->validated());
        $viewer = $this->viewer($request);

        return MobileApiResponse::paginated(
            $paginator->through(fn ($campaign) => CampaignResource::make($campaign)->resolve($request)),
            'Campaigns retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * Show an active campaign for mobile discovery.
     *
     * Public endpoint.
     *
     * @urlParam campaign string required The campaign identifier.
     */
    public function showCampaign(Request $request, string $campaign): JsonResponse
    {
        $model = $this->campaignService->findPublicCampaign($campaign);

        if (! $model) {
            return MobileApiResponse::error('not_found', 'The requested campaign could not be found.', null, 404);
        }

        $viewer = $this->viewer($request);

        return MobileApiResponse::success(
            CampaignResource::make($model)->resolve($request),
            'Campaign retrieved successfully.',
            $this->viewerMeta($viewer),
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
     */
    public function categories(CategoryDiscoveryRequest $request): JsonResponse
    {
        $paginator = $this->categoryService->discover($request->validated());
        $viewer = $this->viewer($request);

        return MobileApiResponse::paginated(
            $paginator->through(fn ($category) => CategoryResource::make($category)->resolve($request)),
            'Categories retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    private function viewer(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewerMeta(?User $user): array
    {
        if ($user === null) {
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
