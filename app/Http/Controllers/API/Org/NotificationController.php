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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function __construct(private OrgNotificationService $service) {}

    public function index(NotificationFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAnyOrganization', Notification::class);

        $notifications = $this->service->paginate($request->query(), $this->organizationId());

        return OrgNotificationResource::collection($notifications);
    }

    public function store(NotificationRequest $request): OrgNotificationResource
    {
        $this->authorize('createOrganization', Notification::class);

        $notification = $this->service->create(
            $request->validated(),
            $this->organizationId(),
            (string) auth()->id(),
        );

        return OrgNotificationResource::make($notification->loadMissing('createdBy'));
    }

    public function show(Notification $notification): OrgNotificationResource
    {
        $this->authorize('viewOrganization', $notification);

        return OrgNotificationResource::make($notification->loadMissing('createdBy'));
    }

    public function update(NotificationRequest $request, Notification $notification): OrgNotificationResource
    {
        $this->authorize('updateOrganization', $notification);

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
        $this->authorize('deleteOrganization', $notification);

        $notification->delete();

        return response()->noContent();
    }

    public function updateReadState(NotificationReadStateRequest $request, Notification $notification): OrgNotificationResource
    {
        $this->authorize('updateReadStateOrganization', $notification);

        $notification = $this->service->updateReadState(
            $notification,
            $request->validated('status'),
        );

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    public function resend(Notification $notification): OrgNotificationResource
    {
        $this->authorize('resendOrganization', $notification);

        $notification = $this->service->resend($notification);

        return OrgNotificationResource::make($notification->refresh()->loadMissing('createdBy'));
    }

    private function organizationId(): string
    {
        $organizationId = (string) auth()->user()?->organization_id;
        if ($organizationId === '') {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }
}
