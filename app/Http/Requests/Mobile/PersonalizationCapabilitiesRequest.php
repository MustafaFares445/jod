<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalizationCapabilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'capabilityIds' => ['required', 'array', 'max:20'],
            'capabilityIds.*' => [
                'string',
                'distinct',
                Rule::exists('capabilities', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
        ];
    }
}
