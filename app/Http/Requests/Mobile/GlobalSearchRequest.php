<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Support\SearchFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $search = SearchFilter::fromArray($this->query());

        if ($search !== '') {
            $this->merge(['search' => $search]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'searchQueries' => ['nullable', 'string', 'max:255'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(['all', 'accounts', 'posts', 'campaigns'])],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
            'perType' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
