<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CompanyRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            'ownerName' => ['required', 'string', 'max:255'],
            'organizationNumber' => ['required', 'string', 'max:100', 'unique:organizations,organization_number'],
            'registrationNumber' => ['required', 'string', 'max:100', 'unique:organizations,registration_number'],
            'bankAccountNumber' => ['required', 'string', 'max:100'],
            'companyEmail' => ['required', 'email', 'max:255', 'unique:organizations,email', 'unique:users,email'],
            'companyPhone' => ['required', 'string', 'max:30'],
            'location' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
