<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalizationInterestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'categoryIds' => ['required', 'array', 'max:20'],
            'categoryIds.*' => [
                'string',
                'distinct',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
        ];
    }
}
