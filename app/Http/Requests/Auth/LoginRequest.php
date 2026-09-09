<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'userType' => ['required', 'string', Rule::in(['admin', 'companies'])],
            'fcmToken' => ['nullable', 'string', 'max:512'],
            'deviceId' => ['nullable', 'string', 'max:255'],
            'appVersion' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تتكون من 8 أحرف على الأقل.',
            'userType.required' => 'نوع الحساب مطلوب.',
            'userType.in' => 'نوع الحساب المحدد غير صالح.',
        ];
    }
}
