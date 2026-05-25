<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\ApplicantFilterRequest;
use App\Http\Requests\Org\ApplicantRequest;
use App\Http\Resources\ApplicantResource;
use App\Models\CampaignApplication;
use App\Services\ApplicantService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ApplicantController extends Controller
{
    public function __construct(private ApplicantService $service) {}

    public function index(ApplicantFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorizeOrgPermission('org.applicants.view');

        $applicants = $this->service->paginate($request->query(), $this->organizationId());

        return ApplicantResource::collection($applicants);
    }

    public function store(ApplicantRequest $request): ApplicantResource
    {
        $this->authorizeOrgPermission('org.applicants.create');

        $applicant = $this->service->create(
            $request->validated(),
            $this->organizationId(),
            (int) auth()->id(),
        );

        return ApplicantResource::make($applicant);
    }

    public function show(CampaignApplication $applicant): ApplicantResource
    {
        $this->authorizeOrgPermission('org.applicants.view');
        $this->assertSameOrganization((int) $applicant->organization_id);

        return ApplicantResource::make($applicant);
    }

    public function update(ApplicantRequest $request, CampaignApplication $applicant): ApplicantResource
    {
        $this->authorizeOrgPermission('org.applicants.update');
        $this->assertSameOrganization((int) $applicant->organization_id);

        $applicant = $this->service->update(
            $applicant,
            $request->validated(),
            $this->organizationId(),
        );

        return ApplicantResource::make($applicant);
    }

    public function destroy(CampaignApplication $applicant): Response
    {
        $this->authorizeOrgPermission('org.applicants.delete');
        $this->assertSameOrganization((int) $applicant->organization_id);

        $applicant->delete();

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
