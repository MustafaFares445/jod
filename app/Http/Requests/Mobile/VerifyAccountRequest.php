<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'البريد الإلكتروني أو رقم الموبايل مطلوب.',
            'code.required' => 'رمز التحقق مطلوب.',
            'code.size' => 'رمز التحقق يجب أن يتكون من 6 أرقام.',
            'code.regex' => 'رمز التحقق يجب أن يتكون من 6 أرقام.',
        ];
    }
}
