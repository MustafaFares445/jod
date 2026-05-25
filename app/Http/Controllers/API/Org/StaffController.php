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

class StaffController extends Controller
{
    public function __construct(private OrganizationStaffService $service) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->authorize('viewAny', OrganizationStaff::class);

        $organization = $user->organization;
        if (!$organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $filters = [
            'role' => $request->input('filter.role'),
            'sort' => $request->input('sort', '-invitedAt'),
        ];

        $staff = $this->service->getStaff($organization, $filters, $request->integer('perPage', 20));

        return StaffResource::collection($staff);
    }

    public function store(StaffRequest $request): StaffResource
    {
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->authorize('create', OrganizationStaff::class);

        $organization = $user->organization;
        if (!$organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $staff = $this->service->inviteStaff($organization, $request->validated());

        return StaffResource::make($staff->load('role'));
    }

    public function show(OrganizationStaff $staff): StaffResource
    {
        $this->authorize('view', $staff);

        return StaffResource::make($staff->load('role'));
    }

    public function update(StaffRequest $request, OrganizationStaff $staff): StaffResource
    {
        $this->authorize('update', $staff);

        $updated = $this->service->updateStaff($staff, $request->validated());

        return StaffResource::make($updated->load('role'));
    }

    public function destroy(OrganizationStaff $staff): Response
    {
        $this->authorize('delete', $staff);

        $this->service->removeStaff($staff);

        return response()->noContent();
    }
}
