<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\CampaignDiscoveryRequest;
use App\Http\Requests\Mobile\CategoryDiscoveryRequest;
use App\Http\Requests\Mobile\PostDiscoveryRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\Mobile\MobileCampaignResource;
use App\Http\Resources\Mobile\MobileHomePostResource;
use App\Http\Resources\Mobile\MobilePublisherResource;
use App\Models\Article;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\CategoryService;
use App\Services\Mobile\PublisherService;
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
        private readonly PublisherService $publisherService,
    ) {}

    /**
     * List public posts for mobile discovery.
     *
     * Public endpoint. A valid Sanctum bearer token is optional and enriches
     * viewer-specific fields such as saved and application state.
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
     * Show a public mobile publisher. Publisher identifiers are the same values
     * emitted by MobileHomePostResource: organization id for organization-backed
     * content, otherwise the individual author id.
     */
    public function showPublisher(Request $request, string $publisher): JsonResponse
    {
        $model = $this->publisherService->findPublic($publisher);

        if ($model === null) {
            return MobileApiResponse::error('not_found', 'The requested publisher could not be found.', null, 404);
        }

        $viewer = $this->viewer($request);

        return MobileApiResponse::success(
            MobilePublisherResource::make($model)->resolve($request),
            'Publisher retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * List public posts belonging to one mobile publisher.
     */
    public function publisherPosts(PostDiscoveryRequest $request, string $publisher): JsonResponse
    {
        $model = $this->publisherService->findPublic($publisher);

        if ($model === null) {
            return MobileApiResponse::error('not_found', 'The requested publisher could not be found.', null, 404);
        }

        $viewer = $this->viewer($request);
        $paginator = $this->publisherService->paginatePosts($model, $request->validated(), $viewer);

        return MobileApiResponse::paginated(
            $paginator->through(fn ($post) => MobileHomePostResource::make($post)->resolve($request)),
            'Publisher posts retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * List active campaigns for mobile discovery.
     */
    public function campaigns(CampaignDiscoveryRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $paginator = $this->campaignService->discover($request->validated(), $viewer);

        return MobileApiResponse::paginated(
            $paginator->through(fn ($campaign) => MobileCampaignResource::make($campaign)->resolve($request)),
            'Campaigns retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * Show an active campaign for mobile discovery.
     */
    public function showCampaign(Request $request, string $campaign): JsonResponse
    {
        $viewer = $this->viewer($request);
        $model = $this->campaignService->findPublicCampaign($campaign, $viewer);

        if (! $model) {
            return MobileApiResponse::error('not_found', 'The requested campaign could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            MobileCampaignResource::make($model)->resolve($request),
            'Campaign retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * List published articles for mobile discovery.
     */
    public function articles(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('perPage', 20), 100));
        $search = (string) $request->query('search', '');
        $viewer = $this->viewer($request);

        $paginator = Article::query()
            ->where('status', 'published')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return MobileApiResponse::paginated(
            $paginator->through(fn (Article $article) => ArticleResource::make($article)->resolve($request)),
            'Articles retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * Show one published article for mobile discovery.
     */
    public function showArticle(Request $request, string $article): JsonResponse
    {
        $model = Article::query()
            ->whereKey($article)
            ->where('status', 'published')
            ->first();

        if ($model === null) {
            return MobileApiResponse::error('not_found', 'The requested article could not be found.', null, 404);
        }

        $viewer = $this->viewer($request);

        return MobileApiResponse::success(
            ArticleResource::make($model)->resolve($request),
            'Article retrieved successfully.',
            $this->viewerMeta($viewer),
        );
    }

    /**
     * List active categories for mobile discovery.
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
