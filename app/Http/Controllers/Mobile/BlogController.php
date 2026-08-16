<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\BlogDiscoveryRequest;
use App\Http\Resources\Mobile\BlogResource;
use App\Models\Article;
use App\Services\Mobile\BlogService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(private readonly BlogService $service) {}

    public function index(BlogDiscoveryRequest $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn (Article $article) => BlogResource::make($article)->resolve($request)),
            'Blogs retrieved successfully.',
        );
    }

    public function show(Request $request, string $blog): JsonResponse
    {
        $article = $this->service->findPublic($blog);

        if ($article === null) {
            return MobileApiResponse::error('not_found', 'The requested blog could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            BlogResource::make($article)->resolve($request),
            'Blog retrieved successfully.',
        );
    }

    public function categories(): JsonResponse
    {
        return MobileApiResponse::success([
            ['id' => 'awareness'],
            ['id' => 'success_stories'],
            ['id' => 'campaign_updates'],
            ['id' => 'volunteer_guides'],
        ], 'Blog categories retrieved successfully.');
    }
}
