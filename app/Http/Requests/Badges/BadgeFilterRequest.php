<?php

declare(strict_types=1);

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;

class BadgeFilterRequest extends FormRequest
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
            'filter.isActive' => ['sometimes', 'boolean'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'in:name,-name,createdAt,-createdAt,isActive,-isActive'],
        ];
    }
}
