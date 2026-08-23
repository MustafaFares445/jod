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
            'phone' => ['required', 'string', 'regex:/^09\d{8}$/'],
            'campaignTitle' => ['required', 'string', 'max:255'],
            'applicantStatus' => ['required', 'string', 'max:100'],
            'appliedAt' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم موبايل سوري من 10 أرقام ويبدأ بـ 09.',
        ];
    }
}
