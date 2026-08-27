<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationVideoController extends Controller
{
    public function index(Request $request, string $organization): JsonResponse
    {
        $model = $this->publicOrganization($organization);
        $perPage = max(1, min((int) $request->query('perPage', 20), 100));

        $viewer = $this->viewer($request);
        $paginator = Media::query()
            ->with($this->relations($viewer))
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('model_id', $model->id)
            ->where('prop', 'videos')
            ->orderBy('position')
            ->orderBy('created_at')
            ->paginate($perPage);

        return MobileApiResponse::paginated(
            $paginator->through(fn (Media $video) => MediaResource::make($video)->resolve($request)),
            'Organization videos retrieved successfully.',
        );
    }

    public function show(Request $request, string $organization, string $video): JsonResponse
    {
        $model = $this->publicOrganization($organization);

        $viewer = $this->viewer($request);
        $media = Media::query()
            ->with($this->relations($viewer))
            ->whereKey($video)
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('model_id', $model->id)
            ->where('prop', 'videos')
            ->first();

        if ($media === null) {
            return MobileApiResponse::error('not_found', 'The requested video could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            MediaResource::make($media)->resolve($request),
            'Organization video retrieved successfully.',
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

    private function publicOrganization(string $id): Organization
    {
        $organization = Organization::query()
            ->whereKey($id)
            ->where('status', 'active')
            ->first();

        abort_if($organization === null, 404, 'Organization not found');

        return $organization;
    }
}
