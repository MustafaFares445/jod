<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\RoleRequest;
use App\Http\Resources\Org\RoleResource;
use App\Models\OrganizationRole;
use App\Services\OrganizationRoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function __construct(private readonly OrganizationRoleService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrganizationRole::class);
        $organization = $request->user()->organization;

        if ($organization === null) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        $filters = [
            'status' => $request->input('filter.status'),
            'sort' => $request->input('sort', '-updatedAt'),
        ];

        return RoleResource::collection(
            $this->service->getRoles($organization, $filters, $request->integer('perPage', 20)),
        );
    }

    public function store(RoleRequest $request): RoleResource
    {
        $this->authorize('create', OrganizationRole::class);
        $organization = $request->user()->organization;

        if ($organization === null) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        $role = $this->service->createRole(
            $organization,
            $request->validated(),
            (string) $request->user()->id,
        );

        return RoleResource::make($role->loadCount('staff'));
    }

    public function show(OrganizationRole $role): RoleResource
    {
        $this->authorize('view', $role);

        return RoleResource::make($role->loadCount('staff'));
    }

    public function update(RoleRequest $request, OrganizationRole $role): RoleResource
    {
        $this->authorize('update', $role);

        return RoleResource::make($this->service->updateRole(
            $role,
            $request->validated(),
            (string) $request->user()->id,
        ));
    }

    public function destroy(Request $request, OrganizationRole $role): Response
    {
        $this->authorize('delete', $role);
        $this->service->deleteRole($role, (string) $request->user()->id);

        return response()->noContent();
    }
}
