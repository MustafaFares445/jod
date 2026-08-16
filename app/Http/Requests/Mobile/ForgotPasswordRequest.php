<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => $this->input('login', $this->input('phoneNumber')),
        ]);
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
        ];
    }
}
