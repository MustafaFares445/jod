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
            'target' => ['required', 'in:post,campaign'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
