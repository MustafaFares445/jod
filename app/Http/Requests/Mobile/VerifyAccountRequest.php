<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }
}
