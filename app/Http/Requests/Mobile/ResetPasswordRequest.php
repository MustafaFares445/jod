<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => $this->input('login', $this->input('phoneNumber')),
            'password' => $this->input('password', $this->input('newPassword')),
            'password_confirmation' => $this->input(
                'password_confirmation',
                $this->input('confirmPassword'),
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'regex:/^(?:\d{4}|\d{6})$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
