<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;

class DonorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^09\d{8}$/'],
            'city' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم موبايل سوري من 10 أرقام ويبدأ بـ 09.',
        ];
    }
}
