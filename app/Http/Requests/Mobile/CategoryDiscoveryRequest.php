<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class CategoryDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'searchQueries' => ['nullable', 'string', 'max:255'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active'],
            'sort' => ['nullable', 'string', 'in:createdAt,-createdAt'],
        ];
    }
}
