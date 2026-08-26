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

        if (! $isUpdate) {
            return [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
            ];
        }

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'pending', 'approved', 'rejected', 'published', 'archived'])],
        ];
    }
}
