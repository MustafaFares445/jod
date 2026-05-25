<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function rules(): array
    {
        $staffId = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('organization_staff', 'email')->ignore($staffId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'organization_role_id' => ['required', 'exists:organization_roles,id'],
        ];
    }
}
