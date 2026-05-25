<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationStaff;
use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizationStaffService
{
    public function getStaff(Organization $organization, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $organization->staff();

        if (isset($filters['role'])) {
            $query->whereHas('role', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (isset($filters['sort'])) {
            $this->applySorting($query, $filters['sort']);
        }

        return $query->with('role')->paginate($perPage);
    }

    public function inviteStaff(Organization $organization, array $data): OrganizationStaff
    {
        $staff = $organization->staff()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'organization_role_id' => $data['organization_role_id'],
            'status' => 'invited',
            'invited_at' => now(),
        ]);

        $staff->generateInvitationToken();

        $this->logAudit('staff.invited', 'OrganizationStaff', $staff->id, [
            'name' => $staff->name,
            'email' => $staff->email,
            'role_id' => $data['organization_role_id'],
        ]);

        return $staff;
    }

    public function updateStaff(OrganizationStaff $staff, array $data): OrganizationStaff
    {
        $originalData = $staff->only(['name', 'email', 'phone', 'organization_role_id', 'status']);

        $staff->update([
            'name' => $data['name'] ?? $staff->name,
            'email' => $data['email'] ?? $staff->email,
            'phone' => $data['phone'] ?? $staff->phone,
            'organization_role_id' => $data['organization_role_id'] ?? $staff->organization_role_id,
            'status' => $data['status'] ?? $staff->status,
        ]);

        $this->logAudit('staff.updated', 'OrganizationStaff', $staff->id, [
            'from' => $originalData,
            'to' => $staff->only(['name', 'email', 'phone', 'organization_role_id', 'status']),
        ]);

        return $staff;
    }

    public function removeStaff(OrganizationStaff $staff): bool
    {
        $this->logAudit('staff.removed', 'OrganizationStaff', $staff->id, [
            'name' => $staff->name,
            'email' => $staff->email,
        ]);

        return $staff->delete();
    }

    private function applySorting($query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        $fieldMap = ['invitedAt' => 'invited_at', 'name' => 'name', 'acceptedAt' => 'accepted_at'];
        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
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
