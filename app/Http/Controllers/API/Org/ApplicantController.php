<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\ApplicantFilterRequest;
use App\Http\Requests\Org\ApplicantRequest;
use App\Http\Resources\ApplicantResource;
use App\Models\CampaignApplication;
use App\Services\ApplicantService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ApplicantController extends Controller
{
    public function __construct(private ApplicantService $service) {}

    public function index(ApplicantFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CampaignApplication::class);

        $applicants = $this->service->paginate($request->query(), $this->organizationId());

        return ApplicantResource::collection($applicants);
    }

    public function store(ApplicantRequest $request): ApplicantResource
    {
        $this->authorize('create', CampaignApplication::class);

        $applicant = $this->service->create(
            $request->validated(),
            $this->organizationId(),
            (string) auth()->id(),
        );

        return ApplicantResource::make($applicant);
    }

    public function show(CampaignApplication $applicant): ApplicantResource
    {
        $this->authorize('view', $applicant);

        return ApplicantResource::make($applicant);
    }

    public function update(ApplicantRequest $request, CampaignApplication $applicant): ApplicantResource
    {
        $this->authorize('update', $applicant);

        $applicant = $this->service->update(
            $applicant,
            $request->validated(),
            $this->organizationId(),
        );

        return ApplicantResource::make($applicant);
    }

    public function accept(CampaignApplication $applicant): ApplicantResource
    {
        $this->authorize('update', $applicant);
        return ApplicantResource::make($this->service->accept($applicant, $this->organizationId()));
    }

    public function contact(CampaignApplication $applicant): ApplicantResource
    {
        $this->authorize('update', $applicant);
        return ApplicantResource::make($this->service->contact($applicant, $this->organizationId()));
    }

    public function complete(CampaignApplication $applicant): ApplicantResource
    {
        $this->authorize('update', $applicant);
        return ApplicantResource::make($this->service->complete($applicant, $this->organizationId()));
    }

    public function reject(Request $request, CampaignApplication $applicant): ApplicantResource
    {
        $this->authorize('update', $applicant);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        return ApplicantResource::make($this->service->reject($applicant, $this->organizationId(), (string) $data['reason']));
    }

    public function destroy(CampaignApplication $applicant): Response
    {
        $this->authorize('delete', $applicant);

        $applicant->delete();

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
