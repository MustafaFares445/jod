<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\ProfilePostRequest;
use App\Http\Resources\Mobile\MobileProfilePostResource;
use App\Models\Post;
use App\Services\Mobile\ProfilePostService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;

class ProfilePostController extends Controller
{
    public function __construct(private readonly ProfilePostService $service) {}

    public function index(ProfilePostRequest $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->user(), $request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn (Post $post) => MobileProfilePostResource::make($post)->resolve($request)),
            'Profile posts retrieved successfully.',
        );
    }
}
