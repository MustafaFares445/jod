<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:80',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'city' => ['nullable', 'string', 'min:2', 'max:100'],
            'bio' => ['nullable', 'string', 'max:180'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $email = $this->exists('email') ? $this->input('email') : $this->user()?->email;
            $phone = $this->exists('phone') ? $this->input('phone') : $this->user()?->phone;

            if (! filled($email) && ! filled($phone)) {
                $validator->errors()->add('email', 'An email address or phone number is required.');
                $validator->errors()->add('phone', 'An email address or phone number is required.');
            }
        }];
    }
}
