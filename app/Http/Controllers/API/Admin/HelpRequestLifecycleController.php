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
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpRequestLifecycleController extends Controller
{
    public function __construct(private readonly AdminHelpRequestLifecycleService $service) {}

    /**
     * Backward-compatible combined lifecycle endpoint used by the existing admin dashboard.
     * New integrations should prefer the dedicated urgency/expiration/fulfillment endpoints.
     */
    public function __invoke(Request $request, Post $post): PostResource
    {
        abort_unless(
            $request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::POST_REVIEW, PermissionAction::APPROVE)),
            403,
        );

        $data = $request->validate([
            'urgency' => ['sometimes', Rule::enum(PostUrgency::class)],
            'urgencyReason' => [
                Rule::requiredIf($request->input('urgency') === PostUrgency::Critical->value),
                'nullable',
                'string',
                'max:1000',
            ],
            'expiresAt' => ['sometimes', 'nullable', 'date'],
            'fulfillmentStatus' => ['sometimes', Rule::enum(HelpRequestStatus::class)],
        ]);

        $actor = $this->actor($request);

        // Apply expiration first so reactivating a previously expired request can provide
        // a new future expiration in the same compatibility request.
        if (array_key_exists('expiresAt', $data)) {
            $post = $this->service->updateExpiration($actor, $post, $data['expiresAt']);
        }

        if (array_key_exists('urgency', $data)) {
            $post = $this->service->updateUrgency(
                $actor,
                $post,
                PostUrgency::from($data['urgency']),
                $data['urgencyReason'] ?? $post->urgency_reason,
            );
        }

        if (array_key_exists('fulfillmentStatus', $data)) {
            $post = $this->service->updateFulfillment(
                $actor,
                $post,
                HelpRequestStatus::from($data['fulfillmentStatus']),
            );
        }

        return PostResource::make($post->refresh()->load([
            'organization',
            'campaign',
            'category',
            'requiredCapabilities',
            'images',
            'videos',
            'author',
            'updatedBy',
            'reviewedBy',
            'blockedBy',
            'urgencyReviewedBy',
        ]));
    }

    public function urgency(UpdatePostUrgencyRequest $request, Post $post): PostResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_URGENCY);

        return PostResource::make($this->service->updateUrgency(
            $this->actor($request),
            $post,
            PostUrgency::from($request->validated('urgency')),
            $request->validated('reason'),
        ));
    }

    public function expiration(UpdatePostExpirationRequest $request, Post $post): PostResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_OUTCOMES);

        return PostResource::make($this->service->updateExpiration(
            $this->actor($request),
            $post,
            $request->validated('expiresAt'),
        ));
    }

    public function fulfillment(UpdatePostFulfillmentRequest $request, Post $post): PostResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::HELP_REQUEST, PermissionAction::MANAGE_OUTCOMES);

        return PostResource::make($this->service->updateFulfillment(
            $this->actor($request),
            $post,
            HelpRequestStatus::from($request->validated('status')),
        ));
    }

    private function actor($request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
