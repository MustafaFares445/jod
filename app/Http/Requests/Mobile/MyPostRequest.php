<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MyPostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', 'string', Rule::in(['draft', 'pending', 'published', 'blocked'])],
            'sort' => ['nullable', 'string', Rule::in(['createdAt', '-createdAt', 'updatedAt', '-updatedAt', 'title', '-title'])],
        ];
    }
}
