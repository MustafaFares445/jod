<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Services\Permissions\OrganizationPermissionSyncService;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrganizationRoleService
{
    public function __construct(
        private readonly OrganizationPermissionSyncService $permissionSyncService,
        private readonly PermissionCatalogService $permissionCatalogService,
    ) {}

    public function getRoles(Organization $organization, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $organization->roles()->withCount('staff');

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (isset($filters['sort'])) {
            $this->applySorting($query, $filters['sort']);
        }

        return $query->paginate($perPage);
    }

    public function createRole(Organization $organization, array $data, string $actorUserId): OrganizationRole
    {
        return DB::transaction(function () use ($organization, $data, $actorUserId): OrganizationRole {
            $permissions = $this->normalizePermissions($data['permissions'] ?? []);

            $role = $organization->roles()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'permissions' => $permissions,
                'is_active' => $data['is_active'] ?? true,
                'is_system' => false,
            ]);

            $this->logAudit($actorUserId, 'role.created', 'OrganizationRole', (string) $role->id, [
                'name' => $role->name,
                'permissions' => $permissions,
            ]);

            return $role;
        });
    }

    public function updateRole(OrganizationRole $role, array $data, string $actorUserId): OrganizationRole
    {
        abort_if($role->is_system, Response::HTTP_CONFLICT, 'System roles cannot be modified.');

        return DB::transaction(function () use ($role, $data, $actorUserId): OrganizationRole {
            $originalData = $role->only(['name', 'description', 'permissions', 'is_active']);
            $permissions = $this->normalizePermissions($data['permissions'] ?? $role->permissions ?? []);

            $role->update([
                'name' => $data['name'] ?? $role->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
                'permissions' => $permissions,
                'is_active' => $data['is_active'] ?? $role->is_active,
            ]);

            $this->logAudit($actorUserId, 'role.updated', 'OrganizationRole', (string) $role->id, [
                'from' => $originalData,
                'to' => $role->only(['name', 'description', 'permissions', 'is_active']),
            ]);

            $this->permissionSyncService->syncForRole($role->fresh());

            return $role->fresh()->loadCount('staff');
        });
    }

    public function deleteRole(OrganizationRole $role, string $actorUserId): void
    {
        abort_if($role->is_system, Response::HTTP_CONFLICT, 'System roles cannot be deleted.');
        abort_if(
            $role->staff()->where('status', 'active')->exists(),
            Response::HTTP_CONFLICT,
            'Roles assigned to active staff cannot be deleted.',
        );

        DB::transaction(function () use ($role, $actorUserId): void {
            $this->logAudit($actorUserId, 'role.deleted', 'OrganizationRole', (string) $role->id, [
                'name' => $role->name,
            ]);

            $role->delete();
        });
    }

    /** @param list<string> $permissions */
    private function normalizePermissions(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        $catalog = collect($this->permissionCatalogService->catalog())->keyBy('id');

        foreach ($permissions as $permissionName) {
            abort_unless(
                $catalog->has($permissionName),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "Permission [{$permissionName}] cannot be assigned to organization staff.",
            );

            foreach ($catalog->get($permissionName)['requires'] ?? [] as $requiredPermission) {
                abort_unless(
                    in_array($requiredPermission, $permissions, true),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    "Permission [{$permissionName}] requires [{$requiredPermission}].",
                );
            }
        }

        return $permissions;
    }

    private function applySorting($query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        $fieldMap = [
            'updatedAt' => 'updated_at',
            'permissionsCount' => 'permissions',
            'membersCount' => 'staff_count',
            'name' => 'name',
            'createdAt' => 'created_at',
        ];

        if (! isset($fieldMap[$field])) {
            return;
        }

        if ($field === 'permissionsCount') {
            $query->orderByRaw('JSON_LENGTH(permissions) '.$direction);

            return;
        }

        $query->orderBy($fieldMap[$field], $direction);
    }

    private function logAudit(string $actorUserId, string $action, string $entityType, string $entityId, array $metadata = []): void
    {
        AuditLog::create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'at' => now(),
        ]);
    }
}
