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
        $roles = ['general', 'volunteer', 'job_seeker', 'donor', 'admin'];

        return [
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string', Rule::in(['createdAt', '-createdAt', 'name', '-name', 'lastActiveAt', '-lastActiveAt'])],
            'sortBy' => ['sometimes', 'string'],
            'filter.status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'all'])],
            'filter.role' => ['sometimes', 'string', Rule::in([...$roles, 'all'])],
            'filter.userType' => ['sometimes', 'string', Rule::in([...$roles, 'all'])],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
