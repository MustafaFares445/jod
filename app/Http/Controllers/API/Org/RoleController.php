<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\RoleRequest;
use App\Http\Resources\Org\RoleResource;
use App\Models\OrganizationRole;
use App\Services\OrganizationRoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function __construct(private OrganizationRoleService $service) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->authorize('viewAny', OrganizationRole::class);

        $organization = $user->organization;
        if (!$organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $filters = [
            'status' => $request->input('filter.status'),
            'sort' => $request->input('sort', '-updatedAt'),
        ];

        $roles = $this->service->getRoles($organization, $filters, $request->integer('perPage', 20));

        return RoleResource::collection($roles);
    }

    public function store(RoleRequest $request): RoleResource
    {
        $user = auth()->user();
        if (!$user || !$user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->authorize('create', OrganizationRole::class);

        $organization = $user->organization;
        if (!$organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $role = $this->service->createRole($organization, $request->validated());

        return RoleResource::make($role);
    }

    public function show(OrganizationRole $role): RoleResource
    {
        $this->authorize('view', $role);

        return RoleResource::make($role);
    }

    public function update(RoleRequest $request, OrganizationRole $role): RoleResource
    {
        $this->authorize('update', $role);

        $updated = $this->service->updateRole($role, $request->validated());

        return RoleResource::make($updated);
    }

    public function destroy(OrganizationRole $role): Response
    {
        $this->authorize('delete', $role);

        if (!$this->service->deleteRole($role)) {
            return response()->json(['message' => 'Cannot delete system role'], 422);
        }

        return response()->noContent();
    }
}
