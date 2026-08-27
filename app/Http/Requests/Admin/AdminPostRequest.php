<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'pending', 'published', 'blocked'])],
            'blockReason' => ['required_if:status,blocked', 'nullable', 'string', 'min:3', 'max:1000'],
        ];
    }
}
