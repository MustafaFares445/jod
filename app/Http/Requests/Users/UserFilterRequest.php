<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string'],
            'sortBy' => ['sometimes', 'string'],
            'filter.status' => ['sometimes', 'string', Rule::in('active', 'inactive')],
            'filter.role' => ['sometimes', 'string', Rule::in('general', 'volunteer', 'job_seeker', 'donor')],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
