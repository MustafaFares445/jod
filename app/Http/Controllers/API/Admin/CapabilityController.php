<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CapabilityRequest;
use App\Http\Resources\Admin\CapabilityResource;
use App\Models\Capability;
use App\Models\User;
use App\Services\Admin\AdminCapabilityService;
use App\Support\Admin\AdminPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CapabilityController extends Controller
{
    public function __construct(private readonly AdminCapabilityService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CAPABILITY, PermissionAction::VIEW);
        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $search = trim((string) ($request->query('search') ?? data_get($request->query(), 'filter.search', '')));
        $status = $request->query('status') ?? data_get($request->query(), 'filter.status');
        $query = Capability::query()->withCount('users')
            ->when($status && $status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->orderBy('sort_order')->orderBy('name');
        return CapabilityResource::collection($query->paginate($perPage));
    }

    public function store(CapabilityRequest $request): CapabilityResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CAPABILITY, PermissionAction::CREATE);
        return CapabilityResource::make($this->service->create($this->actor($request), $request->validated()));
    }

    public function show(Request $request, Capability $capability): CapabilityResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CAPABILITY, PermissionAction::VIEW);
        return CapabilityResource::make($capability->loadCount('users'));
    }

    public function update(CapabilityRequest $request, Capability $capability): CapabilityResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CAPABILITY, PermissionAction::UPDATE);
        return CapabilityResource::make($this->service->update($this->actor($request), $capability, $request->validated()));
    }

    public function updateStatus(Request $request, Capability $capability): CapabilityResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CAPABILITY, PermissionAction::UPDATE);
        $data = $request->validate(['status' => ['required', 'in:active,inactive']]);
        return CapabilityResource::make($this->service->setStatus($this->actor($request), $capability, $data['status']));
    }

    public function destroy(Request $request, Capability $capability): Response|CapabilityResource
    {
        AdminPermission::authorize($request->user(), PermissionGroup::CAPABILITY, PermissionAction::DELETE);
        $used = $capability->users()->exists();
        $result = $this->service->delete($this->actor($request), $capability);
        return $used ? CapabilityResource::make($result) : response()->noContent();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }
}
