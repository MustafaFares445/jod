<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Enums\HelpRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\HelpRequestStatusRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\OrganizationHelpRequestService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class HelpRequestController extends Controller
{
    public function __construct(private readonly OrganizationHelpRequestService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAnyOrganization', Post::class);
        return PostResource::collection($this->service->paginate($this->organizationId(), request()->all()));
    }

    public function show(Post $post): PostResource
    {
        $this->authorize('viewOrganization', $post);
        return PostResource::make($this->service->find($this->organizationId(), $post));
    }

    public function updateStatus(HelpRequestStatusRequest $request, Post $post): PostResource
    {
        $this->authorize('updateOrganization', $post);
        $status = HelpRequestStatus::from((string) $request->validated('status'));
        return PostResource::make($this->service->updateStatus($request->user(), $post, $status));
    }

    private function organizationId(): string
    {
        $id = (string) auth()->user()?->organization_id;
        if ($id === '') throw ValidationException::withMessages(['organizationId' => ['Authenticated user is not linked to an organization.']]);
        return $id;
    }
}
