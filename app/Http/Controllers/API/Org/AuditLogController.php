<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogs\AuditLogFilterRequest;
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Resources\AuditLogResource;
use App\Support\Permissions\PermissionNameResolver;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless(
            $user->isOrganizationOwner()
                || $user->can(PermissionNameResolver::resolve(PermissionGroup::ORG_AUDIT_LOG, PermissionAction::VIEW)),
            403,
        );

        $organizationId = (string) $user->organization_id;
        if ($organizationId === '') {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        $queryParams = $request->query();
        $actorUserId = $this->queryParam($queryParams, 'filter.actorUserId');
        $action = $this->queryParam($queryParams, 'filter.action');
        $from = $this->queryParam($queryParams, 'filter.from');
        $to = $this->queryParam($queryParams, 'filter.to');

        $logs = AuditLog::query()
            ->with('actor')
            ->whereHas('actor', fn ($query) => $query->where('organization_id', $organizationId))
            ->when($actorUserId, fn ($query) => $query->where('actor_user_id', $actorUserId))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($from, fn ($query) => $query->whereDate('at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('at', '<=', $to))
            ->orderByDesc('at')
            ->paginate($request->integer('perPage', 20));

        return AuditLogResource::collection($logs);
    }

    private function queryParam(array $queryParams, string $key): mixed
    {
        if (array_key_exists($key, $queryParams)) {
            return $queryParams[$key];
        }

        $flatKey = str_replace('.', '_', $key);
        if (array_key_exists($flatKey, $queryParams)) {
            return $queryParams[$flatKey];
        }

        return data_get($queryParams, $key);
    }
}
