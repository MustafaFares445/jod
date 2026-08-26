<?php

declare(strict_types=1);

namespace App\Http\Requests\Articles;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $article = $this->route('article');
        $articleId = $article instanceof Article ? $article->getKey() : $article;
        $isUpdate = $articleId !== null;

        return [
            'title' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('articles', 'title')->ignore($articleId),
            ],
            'description' => [$isUpdate ? 'sometimes' : 'required', 'string'],
        ];
    }
}
