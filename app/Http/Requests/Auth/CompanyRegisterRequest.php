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
            'companyPhone' => ['required', 'string', 'regex:/^\\+9639\\d{8}$/'],
            'location' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'companyPhone.required' => 'رقم الموبايل الرسمي مطلوب.',
            'companyPhone.regex' => 'رقم الموبايل الرسمي يجب أن يكون رقماً سورياً بصيغة +9639XXXXXXXX.',
            'logo.required' => 'شعار المنظمة مطلوب.',
            'logo.image' => 'شعار المنظمة يجب أن يكون صورة صالحة.',
            'logo.mimes' => 'صيغة شعار المنظمة يجب أن تكون JPG أو JPEG أو PNG أو WebP.',
            'logo.max' => 'حجم شعار المنظمة يجب ألا يتجاوز 5MB.',
        ];
    }
}
