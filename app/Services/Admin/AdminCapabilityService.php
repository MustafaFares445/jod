<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Capability;
use App\Models\User;
use Illuminate\Support\Arr;

class AdminCapabilityService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function create(User $actor, array $data): Capability
    {
        $capability = Capability::query()->create($this->attributes($data));
        $this->audit->record($actor, 'capability.created', 'capability', (string) $capability->id, [], $capability->toArray());
        return $capability->loadCount('users');
    }

    public function update(User $actor, Capability $capability, array $data): Capability
    {
        $old = $capability->only(['name', 'slug', 'status', 'sort_order']);
        $capability->fill($this->attributes($data))->save();
        $this->audit->record($actor, 'capability.updated', 'capability', (string) $capability->id, $old, $capability->only(['name', 'slug', 'status', 'sort_order']));
        return $capability->refresh()->loadCount('users');
    }

    public function setStatus(User $actor, Capability $capability, string $status): Capability
    {
        return $this->update($actor, $capability, ['status' => $status]);
    }

    public function delete(User $actor, Capability $capability): Capability
    {
        if ($capability->users()->exists()) return $this->setStatus($actor, $capability, 'inactive');
        $snapshot = $capability->toArray();
        $capability->delete();
        $this->audit->record($actor, 'capability.deleted', 'capability', (string) $capability->id, $snapshot, []);
        return $capability;
    }

    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'slug', 'status']);
        if (array_key_exists('sortOrder', $data)) $attributes['sort_order'] = $data['sortOrder'];
        $attributes['status'] ??= 'active';
        $attributes['sort_order'] ??= 0;
        return $attributes;
    }
}
