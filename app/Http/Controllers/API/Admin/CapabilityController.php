<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CapabilityResource;
use App\Models\Capability;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CapabilityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAction($request, PermissionAction::VIEW);
        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $search = trim((string) ($request->query('filter.search') ?? $request->query('filter_search') ?? ''));
        $status = $request->query('filter.status') ?? $request->query('filter_status');

        $query = Capability::query()
            ->withCount('users')
            ->when($search !== '', fn (Builder $builder) => $builder->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when(filled($status) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->orderBy('sort_order')
            ->orderBy('name');

        return CapabilityResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, Capability $capability): CapabilityResource
    {
        $this->authorizeAction($request, PermissionAction::VIEW);
        return CapabilityResource::make($capability->loadCount('users'));
    }

    public function store(Request $request): CapabilityResource
    {
        $this->authorizeAction($request, PermissionAction::CREATE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('capabilities', 'slug')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $capability = Capability::query()->create([
            'id' => (string) Str::uuid(),
            'name' => trim($data['name']),
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'status' => $data['status'],
            'sort_order' => (int) ($data['sortOrder'] ?? 0),
        ]);

        return CapabilityResource::make($capability->loadCount('users'));
    }

    public function update(Request $request, Capability $capability): CapabilityResource
    {
        $this->authorizeAction($request, PermissionAction::UPDATE);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'string', 'max:120', 'alpha_dash', Rule::unique('capabilities', 'slug')->ignore($capability->id)],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'sortOrder' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);

        $updates = [];
        if (array_key_exists('name', $data)) $updates['name'] = trim($data['name']);
        if (array_key_exists('slug', $data)) $updates['slug'] = $data['slug'];
        if (array_key_exists('status', $data)) $updates['status'] = $data['status'];
        if (array_key_exists('sortOrder', $data)) $updates['sort_order'] = (int) $data['sortOrder'];
        $capability->update($updates);

        return CapabilityResource::make($capability->refresh()->loadCount('users'));
    }

    private function authorizeAction(Request $request, PermissionAction $action): void
    {
        abort_unless($request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::CAPABILITY, $action)), 403);
    }
}
