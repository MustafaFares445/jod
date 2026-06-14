<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\NotificationReadStateRequest;
use App\Http\Requests\Org\NotificationRequest;
use App\Http\Resources\OrgNotificationResource;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Notification::class);

        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $sort = (string) ($this->queryParam($request, 'sort') ?? '-sentAt');

        $query = Notification::query()
            ->with('createdBy')
            ->when(($mailbox = $this->queryParam($request, 'filter.mailbox')) && $mailbox !== 'all', fn (Builder $builder) => $builder->where('mailbox', $mailbox))
            ->when(($status = $this->queryParam($request, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($category = $this->queryParam($request, 'filter.category')) && $category !== 'all', fn (Builder $builder) => $builder->where('category', $category))
            ->when(($scope = $this->queryParam($request, 'filter.recipientScope')) && $scope !== 'all', fn (Builder $builder) => $builder->where('recipient_scope', $scope))
            ->when(($date = $this->queryParam($request, 'filter.date')) && $date !== 'all', function (Builder $builder) use ($date): void {
                if ($date === 'today') {
                    $builder->whereDate('created_at', now()->toDateString());

                    return;
                }

                if ($date === 'last_7_days') {
                    $builder->where('created_at', '>=', now()->subDays(7));
                }
            });

        match ($sort) {
            'sentAt' => $query->orderBy('sent_at'),
            '-sentAt' => $query->orderByDesc('sent_at'),
            default => $query->orderByDesc('sent_at'),
        };

        return OrgNotificationResource::collection($query->paginate($perPage));
    }

    public function store(NotificationRequest $request): JsonResponse
    {
        $this->authorize('create', Notification::class);

        $data = $request->validated();

        $notification = Notification::query()->create([
            'id' => (string) Str::uuid(),
            'title' => $data['title'],
            'body' => $data['body'],
            'mailbox' => 'sent',
            'status' => 'sent',
            'category' => $data['category'],
            'recipient_scope' => $data['recipientScope'] ?? 'all',
            'recipient_label' => $data['recipientLabel'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'reference_label' => $data['referenceLabel'] ?? null,
            'reference_path' => $data['referencePath'] ?? null,
            'creator_id' => auth()->id(),
            'sent_at' => now(),
        ]);

        return OrgNotificationResource::make($notification->loadMissing('createdBy'))->response()->setStatusCode(201);
    }

    public function show(Notification $notification): OrgNotificationResource
    {
        $this->authorize('view', $notification);

        return OrgNotificationResource::make($notification->loadMissing('createdBy'));
    }

    public function update(NotificationRequest $request, Notification $notification): OrgNotificationResource
    {
        $this->authorize('update', $notification);

        $data = $request->validated();

        $notification->update([
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'],
            'recipient_scope' => $data['recipientScope'] ?? $notification->recipient_scope,
            'recipient_label' => $data['recipientLabel'] ?? $notification->recipient_label,
            'priority' => $data['priority'] ?? $notification->priority,
            'reference_label' => $data['referenceLabel'] ?? $notification->reference_label,
            'reference_path' => $data['referencePath'] ?? $notification->reference_path,
        ]);

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    public function destroy(Notification $notification): Response
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->noContent();
    }

    public function updateReadState(NotificationReadStateRequest $request, Notification $notification): OrgNotificationResource
    {
        $this->authorize('updateReadState', $notification);

        $status = $request->validated('status');
        $notification->update([
            'status' => $status,
            'read_at' => $status === 'read' ? now() : null,
        ]);

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    public function resend(Notification $notification): OrgNotificationResource
    {
        $this->authorize('resend', $notification);

        $notification->update([
            'mailbox' => 'sent',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    private function queryParam(Request $request, string $key): mixed
    {
        $queryParams = $request->query();

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
