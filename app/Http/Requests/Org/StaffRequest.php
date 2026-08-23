<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Models\OrganizationStaff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('organizationRoleId') && ! $this->has('organization_role_id')) {
            $this->merge([
                'organization_role_id' => $this->input('organizationRoleId'),
            ]);
        }
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $staffId = $staff instanceof OrganizationStaff ? $staff->getKey() : $staff;
        $isUpdate = $staff !== null;
        $organizationId = $this->user()?->organization_id;

        $profileFieldsRequired = ! $isUpdate || ! $this->has('status');

        return [
            'name' => [Rule::requiredIf($profileFieldsRequired), 'string', 'max:255'],
            'email' => [
                Rule::requiredIf($profileFieldsRequired),
                'email',
                'max:255',
                Rule::unique('organization_staff', 'email')->ignore($staffId),
            ],
            'phone' => [
                Rule::requiredIf($profileFieldsRequired),
                'string',
                'regex:/^09\d{8}$/',
            ],
            'organization_role_id' => [
                Rule::requiredIf($profileFieldsRequired),
                'string',
                Rule::exists('organization_roles', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'status' => ['sometimes', Rule::in(['invited', 'active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم موبايل سوري من 10 أرقام ويبدأ بـ 09.',
        ];
    }
}
