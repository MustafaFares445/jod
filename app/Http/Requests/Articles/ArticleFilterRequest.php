<?php

declare(strict_types=1);

namespace App\Http\Requests\Articles;

use Illuminate\Foundation\Http\FormRequest;

class ArticleFilterRequest extends FormRequest
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
            'filter.status' => ['sometimes', 'in:draft,published'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'in:title,-title,createdAt,-createdAt,publishedAt,-publishedAt'],
        ];
    }
}
