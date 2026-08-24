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
}
