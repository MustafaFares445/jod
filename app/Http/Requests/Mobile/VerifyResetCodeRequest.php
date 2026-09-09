<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class VerifyResetCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'البريد الإلكتروني أو رقم الموبايل مطلوب.',
            'code.required' => 'رمز إعادة تعيين كلمة المرور مطلوب.',
            'code.size' => 'رمز إعادة تعيين كلمة المرور يجب أن يتكون من 6 أرقام.',
        ];
    }
}
