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
            'companyName' => ['sometimes', 'required', 'string', 'max:255'],
            'ownerName' => ['sometimes', 'required', 'string', 'max:255'],
            'organizationNumber' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('organizations', 'organization_number')->ignore($organizationId)],
            'registrationNumber' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('organizations', 'registration_number')->ignore($organizationId)],
            'bankAccountNumber' => ['sometimes', 'required', 'string', 'max:100'],
            'companyEmail' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('organizations', 'email')->ignore($organizationId)],
            'companyPhone' => ['sometimes', 'required', 'string', 'max:30'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
