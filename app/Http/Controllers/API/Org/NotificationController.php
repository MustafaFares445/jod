<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\NotificationFilterRequest;
use App\Http\Requests\Org\NotificationReadStateRequest;
use App\Http\Requests\Org\NotificationRequest;
use App\Http\Resources\OrgNotificationResource;
use App\Models\Notification;
use App\Services\OrgNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function __construct(private OrgNotificationService $service) {}

    public function index(NotificationFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorizeOrgPermission('org.notifications.view');

        $notifications = $this->service->paginate($request->query(), $this->organizationId());

        return OrgNotificationResource::collection($notifications);
    }

    public function store(NotificationRequest $request): OrgNotificationResource
    {
        $this->authorizeOrgPermission('org.notifications.create');

        $notification = $this->service->create(
            $request->validated(),
            $this->organizationId(),
            (int) auth()->id(),
        );

        return OrgNotificationResource::make($notification);
    }

    public function show(Notification $notification): OrgNotificationResource
    {
        $this->authorizeOrgPermission('org.notifications.view');
        $this->assertSameOrganization((int) $notification->organization_id);

        return OrgNotificationResource::make($notification->loadMissing('createdBy'));
    }

    public function update(NotificationRequest $request, Notification $notification): OrgNotificationResource
    {
        $this->authorizeOrgPermission('org.notifications.update');
        $this->assertSameOrganization((int) $notification->organization_id);

        $notification->update([
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'category' => $request->validated('category'),
            'recipient_scope' => $request->validated('recipientScope') ?? $notification->recipient_scope,
            'recipient_label' => $request->validated('recipientLabel') ?? null,
            'priority' => $request->validated('priority') ?? $notification->priority,
            'reference_label' => $request->validated('referenceLabel') ?? null,
            'reference_path' => $request->validated('referencePath') ?? null,
        ]);

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    public function destroy(Notification $notification): Response
    {
        $this->authorizeOrgPermission('org.notifications.delete');
        $this->assertSameOrganization((int) $notification->organization_id);

        $notification->delete();

        return response()->noContent();
    }

    public function updateReadState(NotificationReadStateRequest $request, Notification $notification): OrgNotificationResource
    {
        $this->authorizeOrgPermission('org.notifications.update');
        $this->assertSameOrganization((int) $notification->organization_id);

        $status = $request->validated('status');
        $notification->update([
            'status' => $status,
            'read_at' => $status === 'read' ? now() : null,
        ]);

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    public function resend(Notification $notification): OrgNotificationResource
    {
        $this->authorizeOrgPermission('org.notifications.update');
        $this->assertSameOrganization((int) $notification->organization_id);

        $notification->update([
            'mailbox' => 'sent',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    private function organizationId(): int
    {
        $organizationId = (int) auth()->user()?->organization_id;
        if ($organizationId <= 0) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }

    private function authorizeOrgPermission(string $permission): void
    {
        if (!auth()->user()?->can($permission)) {
            throw new AuthorizationException();
        }
    }

    private function assertSameOrganization(int $organizationId): void
    {
        if ($organizationId !== $this->organizationId()) {
            throw new AuthorizationException();
        }
    }
}
