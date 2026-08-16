<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        if (! filled($name)) {
            $name = trim(implode(' ', array_filter([
                $this->input('firstName'),
                $this->input('lastName'),
            ], static fn (mixed $value): bool => filled($value))));
        }

        $this->merge([
            'name' => $name,
            'phone' => $this->input('phone', $this->input('phoneNumber')),
            'password_confirmation' => $this->input(
                'password_confirmation',
                $this->input('confirmPassword'),
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => [
                'nullable',
                'required_without:email',
                'string',
                'max:20',
                Rule::unique('users', 'phone'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
