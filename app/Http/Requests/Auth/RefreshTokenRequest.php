<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'refreshToken' => ['required', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'refreshToken.required' => 'رمز تحديث الجلسة مطلوب.',
            'refreshToken.string' => 'رمز تحديث الجلسة غير صالح.',
            'refreshToken.max' => 'رمز تحديث الجلسة غير صالح.',
        ];
    }
}
