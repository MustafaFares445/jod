<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\User;
use App\Services\Permissions\OrganizationPermissionSyncService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationRoleService
{
    public function __construct(
        private readonly OrganizationPermissionSyncService $permissionSyncService,
    ) {}

    public function getRoles(Organization $organization, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $organization->roles();

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
            $role = $organization->roles()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'permissions' => array_values(array_unique($data['permissions'] ?? [])),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->logAudit($actorUserId, 'role.created', 'OrganizationRole', (string) $role->id, ['name' => $role->name]);

            return $role;
        });
    }

    public function updateRole(OrganizationRole $role, array $data, string $actorUserId): OrganizationRole
    {
        return DB::transaction(function () use ($role, $data, $actorUserId): OrganizationRole {
            $originalData = $role->only(['name', 'description', 'permissions', 'is_active']);

            $role->update([
                'name' => $data['name'] ?? $role->name,
                'description' => $data['description'] ?? $role->description,
                'permissions' => array_values(array_unique($data['permissions'] ?? $role->permissions ?? [])),
                'is_active' => $data['is_active'] ?? $role->is_active,
            ]);

            $this->logAudit($actorUserId, 'role.updated', 'OrganizationRole', (string) $role->id, [
                'from' => $originalData,
                'to' => $role->only(['name', 'description', 'permissions', 'is_active']),
            ]);

            $this->permissionSyncService->syncForRole($role->fresh());

            return $role;
        });
    }

    public function deleteRole(OrganizationRole $role, string $actorUserId): bool
    {
        return DB::transaction(function () use ($role, $actorUserId): bool {
            if ($role->is_system) {
                return false;
            }

            /** @var Collection<int, User> $affectedUsers */
            $affectedUsers = $role->staff()
                ->with('user')
                ->whereNotNull('user_id')
                ->get()
                ->pluck('user')
                ->filter();

            if ($role->staff()->exists()) {
                $defaultRole = $role->organization->roles()
                    ->whereIn('name', ['المشاهد', 'Viewer'])
                    ->first();

                if ($defaultRole !== null) {
                    $role->staff()->update(['organization_role_id' => $defaultRole->id]);
                }
            }

            $this->logAudit($actorUserId, 'role.deleted', 'OrganizationRole', (string) $role->id, ['name' => $role->name]);

            $deleted = $role->delete();

            if ($deleted) {
                $affectedUsers->each(fn (User $user): mixed => $this->permissionSyncService->syncForUser($user));
            }

            return $deleted;
        });
    }

    private function applySorting($query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        $fieldMap = [
            'updatedAt' => 'updated_at',
            'permissionsCount' => 'permissions',
            'membersCount' => 'members_count',
            'name' => 'name',
            'createdAt' => 'created_at',
        ];

        if (isset($fieldMap[$field])) {
            if ($field === 'permissionsCount') {
                $query->orderByRaw('JSON_LENGTH('.$fieldMap[$field].') '.$direction);
            } else {
                $query->orderBy($fieldMap[$field], $direction);
            }
        }
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
