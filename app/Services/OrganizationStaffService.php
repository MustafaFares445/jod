<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Services\Permissions\OrganizationPermissionSyncService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrganizationStaffService
{
    public function __construct(
        private readonly OrganizationPermissionSyncService $permissionSyncService,
        private readonly NotificationEventService $notifications,
    ) {}

    public function getStaff(Organization $organization, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $organization->staff();

        if (isset($filters['role'])) {
            $query->whereHas('role', function ($query) use ($filters): void {
                $query->where('name', $filters['role']);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['sort'])) {
            $this->applySorting($query, $filters['sort']);
        }

        return $query->with('role')->paginate($perPage);
    }

    public function inviteStaff(Organization $organization, array $data, string $actorUserId): OrganizationStaff
    {
        $role = $this->roleForOrganization($organization, (string) $data['organization_role_id']);

        return DB::transaction(function () use ($organization, $data, $actorUserId, $role): OrganizationStaff {
            $staff = $organization->staff()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'organization_role_id' => $role->id,
                'status' => 'invited',
                'invited_at' => now(),
            ]);

            $staff->generateInvitationToken();

            $this->logAudit($actorUserId, 'staff.invited', 'OrganizationStaff', (string) $staff->id, [
                'name' => $staff->name,
                'email' => $staff->email,
                'role_id' => $role->id,
            ]);

            $invitedUser = User::query()->where('email', $staff->email)->first();
            if ($invitedUser !== null) {
                $this->notifications->notifyUser(
                    $invitedUser,
                    NotificationEventType::StaffInvited,
                    'دعوة للانضمام إلى فريق مؤسسة',
                    "تمت دعوتك للانضمام إلى فريق {$organization->name} بدور {$role->name}.",
                    'staff',
                    'high',
                    $organization->name,
                    '/organization/staff',
                    (string) $organization->id,
                    $actorUserId,
                );
            }

            return $staff->load('role');
        });
    }

    public function updateStaff(OrganizationStaff $staff, array $data, string $actorUserId): OrganizationStaff
    {
        $staff->loadMissing(['organization', 'role', 'user']);
        $targetRole = array_key_exists('organization_role_id', $data)
            ? $this->roleForOrganization($staff->organization, (string) $data['organization_role_id'])
            : $staff->role;
        $targetStatus = $data['status'] ?? $staff->status;

        return DB::transaction(function () use ($staff, $data, $actorUserId, $targetRole, $targetStatus): OrganizationStaff {
            $this->guardLastOwnerTransition($staff, $targetRole, $targetStatus);
            $originalData = $staff->only(['name', 'email', 'phone', 'organization_role_id', 'status']);
            $originalRoleId = $staff->organization_role_id;

            $staff->update([
                'name' => $data['name'] ?? $staff->name,
                'email' => $data['email'] ?? $staff->email,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $staff->phone,
                'organization_role_id' => $targetRole?->id,
                'status' => $targetStatus,
            ]);

            $this->logAudit($actorUserId, 'staff.updated', 'OrganizationStaff', (string) $staff->id, [
                'from' => $originalData,
                'to' => $staff->only(['name', 'email', 'phone', 'organization_role_id', 'status']),
            ]);

            if ($staff->user !== null) {
                $this->permissionSyncService->syncForUser($staff->user);

                if ((string) $originalRoleId !== (string) $targetRole?->id) {
                    $this->notifications->notifyUser(
                        $staff->user,
                        NotificationEventType::StaffRoleChanged,
                        'تم تغيير دورك في المؤسسة',
                        "تم تغيير دورك في {$staff->organization->name} إلى {$targetRole?->name}.",
                        'staff',
                        'high',
                        $staff->organization->name,
                        '/organization/staff',
                        (string) $staff->organization_id,
                        $actorUserId,
                    );
                }
            }

            return $staff->fresh()->load('role');
        });
    }

    public function removeStaff(OrganizationStaff $staff, string $actorUserId): void
    {
        $staff->loadMissing(['organization', 'role', 'user']);

        abort_if(
            $staff->user_id !== null && (string) $staff->user_id === $actorUserId,
            Response::HTTP_CONFLICT,
            'You cannot remove your own staff membership.',
        );

        DB::transaction(function () use ($staff, $actorUserId): void {
            if ($staff->isOwner()) {
                abort_if(
                    $this->activeOwnerCount($staff->organization) <= 1,
                    Response::HTTP_CONFLICT,
                    'The final active organization owner cannot be removed.',
                );
            }

            $user = $staff->user;
            $organizationId = (string) $staff->organization_id;
            $organizationName = (string) $staff->organization->name;

            $this->logAudit($actorUserId, 'staff.removed', 'OrganizationStaff', (string) $staff->id, [
                'name' => $staff->name,
                'email' => $staff->email,
            ]);

            $staff->delete();

            if ($user !== null) {
                $this->permissionSyncService->syncForUser($user);
                $this->notifications->notifyUser(
                    $user,
                    NotificationEventType::StaffRemoved,
                    'تمت إزالتك من فريق المؤسسة',
                    "تمت إزالة عضويتك من فريق {$organizationName}.",
                    'staff',
                    'high',
                    $organizationName,
                    null,
                    $organizationId,
                    $actorUserId,
                );
            }
        });
    }

    private function guardLastOwnerTransition(
        OrganizationStaff $staff,
        ?OrganizationRole $targetRole,
        string $targetStatus,
    ): void {
        if (! $staff->isOwner()) {
            return;
        }

        $remainsOwner = $targetStatus === 'active'
            && $targetRole !== null
            && $targetRole->is_active
            && $targetRole->is_system;

        abort_if(
            ! $remainsOwner && $this->activeOwnerCount($staff->organization) <= 1,
            Response::HTTP_CONFLICT,
            'The final active organization owner cannot be deactivated or demoted.',
        );
    }

    private function activeOwnerCount(Organization $organization): int
    {
        return $organization->staff()
            ->where('status', 'active')
            ->whereHas('role', function ($query): void {
                $query->where('is_active', true)->where('is_system', true);
            })
            ->lockForUpdate()
            ->count();
    }

    private function roleForOrganization(Organization $organization, string $roleId): OrganizationRole
    {
        $role = $organization->roles()->whereKey($roleId)->first();

        abort_if($role === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Selected role does not belong to the organization.');
        abort_if(! $role->is_active, Response::HTTP_UNPROCESSABLE_ENTITY, 'Selected role is inactive.');

        return $role;
    }

    private function applySorting($query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        $fieldMap = [
            'invitedAt' => 'invited_at',
            'name' => 'name',
            'acceptedAt' => 'accepted_at',
            'status' => 'status',
        ];

        if (isset($fieldMap[$field])) {
            $query->orderBy($fieldMap[$field], $direction);
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
