<?php

declare(strict_types=1);

namespace App\Http\Requests\Articles;

use Illuminate\Foundation\Http\FormRequest;

class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'unique:articles,title,' . ($this->article->id ?? 'NULL')],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:articles,slug,' . ($this->article->id ?? 'NULL')],
            'excerpt' => ['required', 'string', 'max:500'],
            'content' => ['sometimes', 'string'],
            'status' => ['required', 'in:draft,published'],
            'authorName' => ['required', 'string', 'max:255'],
        ];
    }
}
