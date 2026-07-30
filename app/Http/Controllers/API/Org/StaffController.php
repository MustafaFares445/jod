<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\StaffRequest;
use App\Http\Resources\Org\StaffResource;
use App\Models\OrganizationStaff;
use App\Services\OrganizationStaffService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function __construct(private readonly OrganizationStaffService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OrganizationStaff::class);
        $organization = $request->user()->organization;

        if ($organization === null) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        $filters = [
            'role' => $request->input('filter.role'),
            'status' => $request->input('filter.status'),
            'sort' => $request->input('sort', '-invitedAt'),
        ];

        return StaffResource::collection(
            $this->service->getStaff($organization, $filters, $request->integer('perPage', 20)),
        );
    }

    public function store(StaffRequest $request): StaffResource
    {
        $this->authorize('create', OrganizationStaff::class);
        $organization = $request->user()->organization;

        if ($organization === null) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return StaffResource::make($this->service->inviteStaff(
            $organization,
            $request->validated(),
            (string) $request->user()->id,
        ));
    }

    public function show(OrganizationStaff $staff): StaffResource
    {
        $this->authorize('view', $staff);

        return StaffResource::make($staff->load('role'));
    }

    public function update(StaffRequest $request, OrganizationStaff $staff): StaffResource
    {
        $this->authorize('update', $staff);

        return StaffResource::make($this->service->updateStaff(
            $staff,
            $request->validated(),
            (string) $request->user()->id,
        ));
    }

    public function destroy(Request $request, OrganizationStaff $staff): Response
    {
        $this->authorize('delete', $staff);
        $this->service->removeStaff($staff, (string) $request->user()->id);

        return response()->noContent();
    }
}
