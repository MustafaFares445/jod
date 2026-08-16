<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\PostCommentHistoryRequest;
use App\Http\Requests\Mobile\PostCommentRequest;
use App\Http\Resources\Mobile\PostCommentResource;
use App\Services\Mobile\PostCommentService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    public function __construct(private readonly PostCommentService $service) {}

    public function index(PostCommentHistoryRequest $request, string $post): JsonResponse
    {
        try {
            $paginator = $this->service->paginate($post, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->postNotFound();
        }

        return MobileApiResponse::paginated(
            $paginator->through(fn ($comment) => PostCommentResource::make($comment)->resolve($request)),
            'Comments retrieved successfully.',
        );
    }

    public function store(PostCommentRequest $request, string $post): JsonResponse
    {
        try {
            $comment = $this->service->create(
                $request->user(),
                $post,
                $request->validated('body'),
            );
        } catch (ModelNotFoundException) {
            return $this->postNotFound();
        }

        return MobileApiResponse::success(
            PostCommentResource::make($comment)->resolve($request),
            'Comment added successfully.',
        );
    }

    public function update(PostCommentRequest $request, string $post, string $comment): JsonResponse
    {
        try {
            $updated = $this->service->update(
                $request->user(),
                $post,
                $comment,
                $request->validated('body'),
            );
        } catch (ModelNotFoundException) {
            return $this->postNotFound();
        }

        if ($updated === null) {
            return MobileApiResponse::error('not_found', 'The requested comment could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            PostCommentResource::make($updated)->resolve($request),
            'Comment updated successfully.',
        );
    }

    public function destroy(Request $request, string $post, string $comment): JsonResponse
    {
        try {
            $deleted = $this->service->delete($request->user(), $post, $comment);
        } catch (ModelNotFoundException) {
            return $this->postNotFound();
        }

        if (! $deleted) {
            return MobileApiResponse::error('not_found', 'The requested comment could not be found.', null, 404);
        }

        return MobileApiResponse::success(null, 'Comment deleted successfully.');
    }

    private function postNotFound(): JsonResponse
    {
        return MobileApiResponse::error('not_found', 'The requested post could not be found.', null, 404);
    }
}
