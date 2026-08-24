<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Organization;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaDiscoveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('perPage', 20), 100));
        $search = trim((string) $request->query('search', ''));

        $paginator = Media::query()
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('prop', 'videos')
            ->whereIn('model_id', Organization::query()->where('status', 'active')->select('id'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return MobileApiResponse::paginated(
            $paginator->through(fn (Media $video) => MediaResource::make($video)->resolve($request)),
            'Media retrieved successfully.',
        );
    }

    public function show(Request $request, string $video): JsonResponse
    {
        $media = Media::query()
            ->whereKey($video)
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('prop', 'videos')
            ->whereIn('model_id', Organization::query()->where('status', 'active')->select('id'))
            ->first();

        if ($media === null) {
            return MobileApiResponse::error('not_found', 'The requested media could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            MediaResource::make($media)->resolve($request),
            'Media retrieved successfully.',
        );
    }
}
