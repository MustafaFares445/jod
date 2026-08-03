<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('organizations', 'email')->ignore($organizationId),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
