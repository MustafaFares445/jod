<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizationRoleService
{
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

    public function createRole(Organization $organization, array $data): OrganizationRole
    {
        $role = $organization->roles()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->logAudit('role.created', 'OrganizationRole', $role->id, ['name' => $role->name]);

        return $role;
    }

    public function updateRole(OrganizationRole $role, array $data): OrganizationRole
    {
        $originalData = $role->only(['name', 'description', 'permissions', 'is_active']);

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'description' => $data['description'] ?? $role->description,
            'permissions' => $data['permissions'] ?? $role->permissions,
            'is_active' => $data['is_active'] ?? $role->is_active,
        ]);

        $this->logAudit('role.updated', 'OrganizationRole', $role->id, [
            'from' => $originalData,
            'to' => $role->only(['name', 'description', 'permissions', 'is_active']),
        ]);

        return $role;
    }

    public function deleteRole(OrganizationRole $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        $staffWithRole = $role->staff()->count();
        if ($staffWithRole > 0) {
            $defaultRole = $role->organization->roles()->where('name', 'Viewer')->first();
            if ($defaultRole) {
                $role->staff()->update(['organization_role_id' => $defaultRole->id]);
            }
        }

        $this->logAudit('role.deleted', 'OrganizationRole', $role->id, ['name' => $role->name]);

        return $role->delete();
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
                $query->orderByRaw('JSON_LENGTH(' . $fieldMap[$field] . ') ' . $direction);
            } else {
                $query->orderBy($fieldMap[$field], $direction);
            }
        }
    }

    private function logAudit(string $action, string $entityType, int $entityId, array $metadata = []): void
    {
        AuditLog::create([
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'at' => now(),
        ]);
    }
}
