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

class OrganizationVideoController extends Controller
{
    public function index(Request $request, string $organization): JsonResponse
    {
        $model = $this->publicOrganization($organization);
        $perPage = max(1, min((int) $request->query('perPage', 20), 100));

        $paginator = Media::query()
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

        $media = Media::query()
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
