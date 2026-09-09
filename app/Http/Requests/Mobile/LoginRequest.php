<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Email + password is the primary mobile login flow. Phone login remains
     * accepted for backwards compatibility with existing clients.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'nullable',
                'email',
                'max:255',
                'required_without:phone',
                Rule::prohibitedIf(fn (): bool => filled($this->input('phone'))),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'required_without:email',
                Rule::prohibitedIf(fn (): bool => filled($this->input('email'))),
            ],
            'password' => ['required', 'string', 'min:8'],
            'fcmToken' => ['nullable', 'string', 'max:512'],
            'fcmPlatform' => ['nullable', 'string', Rule::in(['ios', 'android'])],
            'deviceId' => ['nullable', 'string', 'max:255'],
            'appVersion' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.required_without' => 'أدخل البريد الإلكتروني أو رقم الموبايل.',
            'email.prohibited' => 'استخدم البريد الإلكتروني أو رقم الموبايل فقط، وليس كليهما.',
            'phone.required_without' => 'أدخل البريد الإلكتروني أو رقم الموبايل.',
            'phone.prohibited' => 'استخدم البريد الإلكتروني أو رقم الموبايل فقط، وليس كليهما.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تتكون من 8 أحرف على الأقل.',
            'fcmPlatform.in' => 'نوع نظام الجهاز غير صالح.',
        ];
    }
}
