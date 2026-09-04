<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\HelpRequestStatus;
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Enums\PostUrgency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePostExpirationRequest;
use App\Http\Requests\Admin\UpdatePostFulfillmentRequest;
use App\Http\Requests\Admin\UpdatePostUrgencyRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\Admin\AdminHelpRequestLifecycleService;
use App\Support\Admin\AdminPermission;

class HelpRequestLifecycleController extends Controller
{
    public function __construct(private readonly AdminHelpRequestLifecycleService $service) {}

    public function urgency(UpdatePostUrgencyRequest $request, Post $post): PostResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_URGENCY);
        return PostResource::make($this->service->updateUrgency($this->actor($request), $post, PostUrgency::from($request->validated('urgency')), $request->validated('reason')));
    }

    public function expiration(UpdatePostExpirationRequest $request, Post $post): PostResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_OUTCOMES);
        return PostResource::make($this->service->updateExpiration($this->actor($request), $post, $request->validated('expiresAt')));
    }

    public function fulfillment(UpdatePostFulfillmentRequest $request, Post $post): PostResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_OUTCOMES);
        return PostResource::make($this->service->updateFulfillment($this->actor($request), $post, HelpRequestStatus::from($request->validated('status'))));
    }

    private function actor($request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }
}
