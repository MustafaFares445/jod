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
            'companyEmail' => ['required', 'email', 'max:255', 'unique:organizations,email'],
            'companyPhone' => ['required', 'string', 'max:30'],
            'organizationType' => ['required', 'string', 'max:100'],
            'registrationNumber' => ['required', 'string', 'max:100', 'unique:organizations,registration_number'],
            'location' => ['required', 'string', 'max:255'],
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'ownerPhone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'establishmentDate' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
