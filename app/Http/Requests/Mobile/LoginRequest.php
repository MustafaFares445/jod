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
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'password' => ['required', 'string', 'min:8'],
            'fcmToken' => ['nullable', 'string', 'max:512'],
            'fcmPlatform' => ['nullable', 'string', Rule::in(['ios', 'android'])],
            'deviceId' => ['nullable', 'string', 'max:255'],
            'appVersion' => ['nullable', 'string', 'max:64'],
        ];
    }
}
