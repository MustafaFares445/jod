<?php

declare(strict_types=1);

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'current_password'],
            'newPassword' => ['required', 'string', Password::min(8), 'confirmed', 'different:currentPassword'],
            'newPassword_confirmation' => ['required', 'string'],
        ];
    }
}
