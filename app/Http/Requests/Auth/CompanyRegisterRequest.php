<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompanyRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $founders = $this->input('founders');

        // Backward compatibility for the previous single-founder contract.
        if ((! is_array($founders) || $founders === []) && ($this->filled('ownerName') || $this->filled('password'))) {
            $founders = [[
                'name' => (string) $this->input('ownerName', ''),
                'email' => (string) $this->input('companyEmail', ''),
                'phone' => (string) $this->input('companyPhone', ''),
                'password' => (string) $this->input('password', ''),
                'password_confirmation' => (string) $this->input('password_confirmation', ''),
            ]];
        }

        if (! is_array($founders)) {
            return;
        }

        $normalizedFounders = array_map(static function (mixed $founder): mixed {
            if (! is_array($founder)) {
                return $founder;
            }

            return [
                ...$founder,
                'name' => trim((string) ($founder['name'] ?? '')),
                'email' => mb_strtolower(trim((string) ($founder['email'] ?? ''))),
                'phone' => trim((string) ($founder['phone'] ?? '')),
            ];
        }, array_values($founders));

        $this->merge(['founders' => $normalizedFounders]);
    }

    public function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            'organizationNumber' => ['required', 'string', 'max:100', 'unique:organizations,organization_number'],
            'registrationNumber' => ['required', 'string', 'max:100', 'unique:organizations,registration_number'],
            'bankAccountNumber' => ['required', 'string', 'max:100'],
            'companyEmail' => ['required', 'email', 'max:255', 'unique:organizations,email', 'unique:users,email'],
            'companyPhone' => ['required', 'string', 'regex:/^\\+9639\\d{8}$/'],
            'location' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'founders' => ['required', 'array', 'min:1', 'max:10'],
            'founders.*.name' => ['required', 'string', 'max:255'],
            'founders.*.email' => [
                'required',
                'email',
                'max:255',
                'distinct:ignore_case',
                Rule::unique('users', 'email'),
            ],
            'founders.*.phone' => [
                'required',
                'string',
                'regex:/^\\+9639\\d{8}$/',
                'distinct',
                Rule::unique('users', 'phone'),
            ],
            'founders.*.password' => ['required', 'string', 'confirmed', Password::min(8)],
            'founders.*.password_confirmation' => ['required', 'string'],
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
            'founders.required' => 'يجب إضافة مؤسس واحد على الأقل.',
            'founders.min' => 'يجب إضافة مؤسس واحد على الأقل.',
            'founders.max' => 'يمكن إضافة 10 مؤسسين كحد أقصى أثناء التسجيل.',
            'founders.*.name.required' => 'اسم المؤسس مطلوب.',
            'founders.*.email.required' => 'البريد الإلكتروني للمؤسس مطلوب.',
            'founders.*.email.distinct' => 'لا يمكن تكرار البريد الإلكتروني بين المؤسسين.',
            'founders.*.email.unique' => 'البريد الإلكتروني لأحد المؤسسين مستخدم مسبقاً.',
            'founders.*.phone.required' => 'رقم موبايل المؤسس مطلوب.',
            'founders.*.phone.regex' => 'رقم موبايل المؤسس يجب أن يكون رقماً سورياً بصيغة +9639XXXXXXXX.',
            'founders.*.phone.distinct' => 'لا يمكن تكرار رقم الموبايل بين المؤسسين.',
            'founders.*.phone.unique' => 'رقم موبايل أحد المؤسسين مستخدم مسبقاً.',
            'founders.*.password.required' => 'كلمة مرور المؤسس مطلوبة.',
            'founders.*.password.confirmed' => 'تأكيد كلمة مرور المؤسس غير متطابق.',
            'founders.*.password_confirmation.required' => 'تأكيد كلمة مرور المؤسس مطلوب.',
        ];
    }
}
