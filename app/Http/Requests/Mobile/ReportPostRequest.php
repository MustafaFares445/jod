<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(['misleading', 'abusive', 'fraud', 'impersonation', 'other'])],
            'details' => ['nullable', 'required_if:reason,other', 'string', 'min:3', 'max:180'],
        ];
    }
}
