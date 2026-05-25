<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DonorFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter.campaignId' => ['sometimes', 'string'],
            'filter.city' => ['sometimes', 'string', 'max:255'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(['donatedAt', '-donatedAt', 'name', '-name'])],
            'sortBy' => ['sometimes', 'string', Rule::in(['date_newest', 'date_oldest', 'name_asc', 'name_desc'])],
        ];
    }
}
