<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\DonorFilterRequest;
use App\Http\Requests\Org\DonorRequest;
use App\Http\Resources\DonorResource;
use App\Models\Donation;
use App\Services\DonorService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DonorController extends Controller
{
    public function __construct(private DonorService $service) {}

    public function index(DonorFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Donation::class);

        $donors = $this->service->paginate($request->query(), $this->organizationId());

        return DonorResource::collection($donors);
    }

    public function store(DonorRequest $request): DonorResource
    {
        $this->authorize('create', Donation::class);

        $donor = $this->service->create(
            $request->validated(),
            $this->organizationId(),
            (string) auth()->id(),
        );

        return DonorResource::make($donor);
    }

    public function show(Donation $donor): DonorResource
    {
        $this->authorize('view', $donor);

        return DonorResource::make($donor);
    }

    public function update(DonorRequest $request, Donation $donor): DonorResource
    {
        $this->authorize('update', $donor);

        $donor = $this->service->update(
            $donor,
            $request->validated(),
            $this->organizationId(),
        );

        return DonorResource::make($donor);
    }

    public function destroy(Donation $donor): Response
    {
        $this->authorize('delete', $donor);

        $donor->delete();

        return response()->noContent();
    }

    private function organizationId(): string
    {
        $organizationId = (string) auth()->user()?->organization_id;
        if ($organizationId === '') {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }
}
