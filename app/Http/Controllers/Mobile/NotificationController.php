<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\NotificationHistoryRequest;
use App\Http\Resources\Mobile\NotificationResource;
use App\Models\User;
use App\Services\Mobile\NotificationService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    /**
     * List the authenticated user's personal mobile notifications.
     *
     * @queryParam page int optional The page number.
     * @queryParam perPage int optional The number of notifications per page, up to 100.
     * @queryParam status string optional Filter by read state. Allowed: unread, read.
     * @queryParam category string optional Filter by notification category.
     * @queryParam priority string optional Filter by priority. Allowed: normal, high.
     *
     * @response array{success: true, message: string, data: array<int, array{id: string, title: string, body: string, category: string, eventType: string|null, priority: string, status: string, isRead: bool, referenceLabel: string|null, referencePath: string|null, sentAt: string|null, readAt: string|null, createdAt: string|null}>, error: null, meta: array{currentPage: int, perPage: int, total: int, lastPage: int}}
     */
    public function index(NotificationHistoryRequest $request): JsonResponse
    {
        $paginator = $this->service->paginateForUser(
            $this->user($request),
            $request->validated(),
        );

        return MobileApiResponse::paginated(
            $paginator->through(fn ($notification) => NotificationResource::make($notification)->resolve($request)),
            'Notifications retrieved successfully.',
        );
    }

    /**
     * Return the authenticated user's unread notification count.
     *
     * @response array{success: true, message: string, data: array{unreadCount: int}, error: null, meta: object}
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return MobileApiResponse::success(
            ['unreadCount' => $this->service->unreadCount($this->user($request))],
            'Unread notification count retrieved successfully.',
        );
    }

    /**
     * Show one personal notification.
     *
     * @urlParam notification string required The notification identifier.
     *
     * @response array{success: true, message: string, data: array{id: string, title: string, body: string, category: string, eventType: string|null, priority: string, status: string, isRead: bool, referenceLabel: string|null, referencePath: string|null, sentAt: string|null, readAt: string|null, createdAt: string|null}, error: null, meta: object}
     */
    public function show(Request $request, string $notification): JsonResponse
    {
        $model = $this->service->findForUser($this->user($request), $notification);

        if ($model === null) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            NotificationResource::make($model)->resolve($request),
            'Notification retrieved successfully.',
        );
    }

    /**
     * Mark one personal notification as read.
     *
     * @urlParam notification string required The notification identifier.
     */
    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = $this->service->markRead($this->user($request), $notification);

        if ($model === null) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            NotificationResource::make($model)->resolve($request),
            'Notification marked as read.',
        );
    }

    /**
     * Mark one personal notification as unread.
     *
     * @urlParam notification string required The notification identifier.
     */
    public function markUnread(Request $request, string $notification): JsonResponse
    {
        $model = $this->service->markUnread($this->user($request), $notification);

        if ($model === null) {
            return $this->notFound();
        }

        return MobileApiResponse::success(
            NotificationResource::make($model)->resolve($request),
            'Notification marked as unread.',
        );
    }

    /**
     * Mark all unread personal notifications as read.
     *
     * @response array{success: true, message: string, data: array{updatedCount: int, unreadCount: int}, error: null, meta: object}
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $updatedCount = $this->service->markAllRead($this->user($request));

        return MobileApiResponse::success(
            [
                'updatedCount' => $updatedCount,
                'unreadCount' => 0,
            ],
            'All notifications marked as read.',
        );
    }

    private function notFound(): JsonResponse
    {
        return MobileApiResponse::error(
            'not_found',
            'The requested notification could not be found.',
            null,
            404,
        );
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
