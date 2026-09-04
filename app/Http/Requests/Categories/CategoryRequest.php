<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,'.($this->category->id ?? 'NULL')],
            'description' => ['required', 'string', 'max:1000'],
            'keywords' => ['sometimes', 'array', 'max:30'],
            'keywords.*' => ['string', 'max:80', 'distinct'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
