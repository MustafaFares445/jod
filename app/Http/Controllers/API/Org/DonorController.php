<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\DonorFilterRequest;
use App\Http\Requests\Org\DonorRequest;
use App\Http\Resources\DonorResource;
use App\Models\Donation;
use App\Services\DonorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DonorController extends Controller
{
    public function __construct(private DonorService $service) {}

    public function index(DonorFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorizeOrgPermission('org.donors.view');

        $donors = $this->service->paginate($request->query(), $this->organizationId());

        return DonorResource::collection($donors);
    }

    public function store(DonorRequest $request): DonorResource
    {
        $this->authorizeOrgPermission('org.donors.create');

        $donor = $this->service->create(
            $request->validated(),
            $this->organizationId(),
            (int) auth()->id(),
        );

        return DonorResource::make($donor);
    }

    public function show(Donation $donor): DonorResource
    {
        $this->authorizeOrgPermission('org.donors.view');
        $this->assertSameOrganization((int) $donor->organization_id);

        return DonorResource::make($donor);
    }

    public function update(DonorRequest $request, Donation $donor): DonorResource
    {
        $this->authorizeOrgPermission('org.donors.update');
        $this->assertSameOrganization((int) $donor->organization_id);

        $donor = $this->service->update(
            $donor,
            $request->validated(),
            $this->organizationId(),
        );

        return DonorResource::make($donor);
    }

    public function destroy(Donation $donor): Response
    {
        $this->authorizeOrgPermission('org.donors.delete');
        $this->assertSameOrganization((int) $donor->organization_id);

        $donor->delete();

        return response()->noContent();
    }

    private function organizationId(): int
    {
        $organizationId = (int) auth()->user()?->organization_id;
        if ($organizationId <= 0) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }

    private function authorizeOrgPermission(string $permission): void
    {
        if (!auth()->user()?->can($permission)) {
            throw new AuthorizationException();
        }
    }

    private function assertSameOrganization(int $organizationId): void
    {
        if ($organizationId !== $this->organizationId()) {
            throw new AuthorizationException();
        }
    }
}
