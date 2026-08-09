<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MyPostRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', 'string', Rule::in(['draft', 'pending', 'active', 'rejected', 'archived'])],
            'sort' => ['nullable', 'string', Rule::in(['createdAt', '-createdAt', 'updatedAt', '-updatedAt', 'title', '-title'])],
        ];
    }
}
