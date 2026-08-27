<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Organization;
use App\Models\User;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaDiscoveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('perPage', 20), 100));
        $search = trim((string) $request->query('search', ''));
        $viewer = $this->viewer($request);

        $paginator = Media::query()
            ->with($this->relations($viewer))
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
        $viewer = $this->viewer($request);
        $media = Media::query()
            ->with($this->relations($viewer))
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

    private function viewer(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user instanceof User ? $user : null;
    }

    /** @return array<int|string, mixed> */
    private function relations(?User $viewer): array
    {
        $relations = ['organization.logoMedia'];

        if ($viewer === null) {
            return $relations;
        }

        $relations['likes'] = static fn (Relation $relation) => $relation->where('user_id', $viewer->id);
        $relations['saves'] = static fn (Relation $relation) => $relation->where('user_id', $viewer->id);

        return $relations;
    }
}
