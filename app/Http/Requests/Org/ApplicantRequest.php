<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;

class ApplicantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+9639\d{8}$/'],
            'campaignTitle' => ['required', 'string', 'max:255'],
            'applicantStatus' => ['required', 'string', 'max:100'],
            'appliedAt' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الهاتف يجب أن يبدأ بـ +963 ثم 9 أرقام، ويجب أن يبدأ الرقم بعد +963 بالرقم 9.',
        ];
    }
}
