<?php

declare(strict_types=1);

namespace App\Support\Permissions;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use Illuminate\Support\Collection;

final class PermissionCatalog
{
    /**
     * @return Collection<int, array{
     *     name: string,
     *     group: PermissionGroup,
     *     action: PermissionAction,
     *     module_key: string,
     *     module_label: string,
     *     section_key: string|null,
     *     section_label: string|null,
     *     label: string,
     *     depth: int
     * }>
     */
    public static function permissions(): Collection
    {
        return collect(PermissionGroup::cases())
            ->sortBy(fn (PermissionGroup $group): string => sprintf(
                '%04d-%04d',
                $group->module()->order(),
                $group->order(),
            ))
            ->values()
            ->flatMap(function (PermissionGroup $group): Collection {
                return collect($group->actions())
                    ->map(function ($action) use ($group): array {
                        $name = PermissionNameResolver::resolve($group, $action);

                        return [
                            'name' => $name,
                            'group' => $group,
                            'action' => $action,
                            'module_key' => $group->moduleKey(),
                            'module_label' => $group->moduleLabel(),
                            'section_key' => $group->sectionKey(),
                            'section_label' => $group->sectionLabel(),
                            'label' => "{$action->label()} {$group->label()}",
                            'depth' => PermissionNameResolver::depth($name),
                        ];
                    });
            })
            ->values();
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return self::permissions()
            ->pluck('name')
            ->values()
            ->all();
    }
}
