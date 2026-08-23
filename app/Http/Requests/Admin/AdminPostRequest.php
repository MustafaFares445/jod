<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('post') !== null;

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'content' => [$isUpdate ? 'sometimes' : 'required_without:description', 'nullable', 'string'],
            'description' => [$isUpdate ? 'sometimes' : 'required_without:content', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
        ];
    }
}
