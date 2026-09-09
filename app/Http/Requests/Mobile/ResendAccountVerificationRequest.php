<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ResendAccountVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'البريد الإلكتروني أو رقم الموبايل مطلوب.',
        ];
    }
}
