<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Enums\PermissionModule;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Support\Collection;

class PermissionCatalogService
{
    public function forUser(User $user): array
    {
        $permissions = PermissionCatalog::permissions();

        $allowed = $user->getAllPermissions()
            ->pluck('name')
            ->flip();

        $modules = $permissions
            ->groupBy('module_key')
            ->map(fn (Collection $modulePermissions): array => $this->formatModule($modulePermissions, $allowed))
            ->sortBy('order')
            ->values();

        $flat = $permissions
            ->mapWithKeys(fn (array $permission): array => [
                $permission['name'] => $allowed->has($permission['name']),
            ])
            ->all();

        return [
            'modules' => $modules->all(),
            'flat' => $flat,
            'granted' => collect($flat)
                ->filter()
                ->keys()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     group: string,
     *     description: string,
     *     action: string,
     *     requires: list<string>,
     *     assignable: bool
     * }>
     */
    public function catalog(): array
    {
        return PermissionCatalog::permissions()
            ->filter(fn (array $permission): bool => $permission['group'] === PermissionGroup::DASHBOARD
                || $permission['group']->module() === PermissionModule::ORGANIZATION)
            ->reject(fn (array $permission): bool => in_array($permission['group'], [
                PermissionGroup::ORG_STAFF,
                PermissionGroup::ORG_ROLE,
                PermissionGroup::ORG_NOTIFICATION,
            ], true))
            ->map(fn (array $permission): array => [
                'id' => $permission['name'],
                'name' => $permission['label'],
                'group' => $permission['group']->label(),
                'description' => $permission['group']->description(),
                'action' => $permission['action']->value,
                'requires' => $this->requiredPermissions($permission['group'], $permission['action']),
                'assignable' => true,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function assignableOrganizationPermissionNames(): array
    {
        return collect($this->catalog())
            ->pluck('id')
            ->values()
            ->all();
    }

    private function formatModule(Collection $modulePermissions, Collection $allowed): array
    {
        /** @var PermissionGroup $firstGroup */
        $firstGroup = $modulePermissions->first()['group'];
        $module = $firstGroup->module();

        return [
            'key' => $module->value,
            'label' => $module->label(),
            'order' => $module->order(),
            'groups' => $modulePermissions
                ->groupBy(fn (array $permission): string => $permission['group']->value)
                ->map(fn (Collection $groupPermissions): array => $this->formatGroup($groupPermissions, $allowed))
                ->sortBy('order')
                ->values()
                ->all(),
        ];
    }

    private function formatGroup(Collection $groupPermissions, Collection $allowed): array
    {
        /** @var PermissionGroup $group */
        $group = $groupPermissions->first()['group'];

        return [
            'key' => $group->value,
            'label' => $group->label(),
            'sectionKey' => $group->sectionKey(),
            'sectionLabel' => $group->sectionLabel(),
            'description' => $group->description(),
            'order' => $group->order(),
            'depth' => $group->depth() + 1,
            'permissions' => $groupPermissions
                ->map(fn (array $permission): array => [
                    'key' => $permission['action']->value,
                    'name' => $permission['name'],
                    'label' => $permission['label'],
                    'allowed' => $allowed->has($permission['name']),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return list<string> */
    private function requiredPermissions(PermissionGroup $group, PermissionAction $action): array
    {
        if ($action === PermissionAction::VIEW || ! in_array(PermissionAction::VIEW, $group->actions(), true)) {
            return [];
        }

        return [PermissionNameResolver::resolve($group, PermissionAction::VIEW)];
    }
}
