<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogs\AuditLogFilterRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $queryParams = $request->query();
        $actorUserId = $this->queryParam($queryParams, 'filter.actorUserId');
        $action = $this->queryParam($queryParams, 'filter.action');
        $from = $this->queryParam($queryParams, 'filter.from');
        $to = $this->queryParam($queryParams, 'filter.to');

        $logs = AuditLog::query()
            ->with('actor')
            ->when($actorUserId, fn ($q) => $q->where('actor_user_id', $actorUserId))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($from, fn ($q) => $q->whereDate('at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('at', '<=', $to))
            ->orderByDesc('at')
            ->paginate($request->get('perPage', 20));

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
