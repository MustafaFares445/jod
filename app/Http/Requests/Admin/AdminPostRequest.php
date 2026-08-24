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
            'summary' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content' => [$isUpdate ? 'sometimes' : 'required_without:description', 'nullable', 'string'],
            'description' => [$isUpdate ? 'sometimes' : 'required_without:content', 'nullable', 'string'],
            'type' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['draft', 'pending', 'approved', 'rejected', 'published', 'archived'])],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', Rule::exists('categories', 'id')],
            'campaign_id' => ['sometimes', 'nullable', Rule::exists('campaigns', 'id')],
            'organization_id' => ['sometimes', 'nullable', Rule::exists('organizations', 'id')],
            'author_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')],
        ];
    }
}
