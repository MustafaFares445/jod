<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Models\OrganizationRole;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof OrganizationRole ? $role->getKey() : $role;
        $permissionCatalog = app(PermissionCatalogService::class);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organization_roles', 'name')
                    ->where('organization_id', $this->user()?->organization_id)
                    ->ignore($roleId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::in($permissionCatalog->assignableOrganizationPermissionNames()),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $selected = collect($this->input('permissions', []))
                ->filter(fn (mixed $permission): bool => is_string($permission))
                ->values();

            $catalog = collect(app(PermissionCatalogService::class)->catalog())
                ->keyBy('id');

            foreach ($selected as $permissionName) {
                $requiredPermissions = $catalog->get($permissionName)['requires'] ?? [];

                foreach ($requiredPermissions as $requiredPermission) {
                    if (! $selected->contains($requiredPermission)) {
                        $validator->errors()->add(
                            'permissions',
                            "Permission [{$permissionName}] requires [{$requiredPermission}].",
                        );
                    }
                }
            }
        }];
    }
}
