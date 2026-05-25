<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'siteName' => ['sometimes', 'string', 'max:255'],
            'allowNewPosts' => ['sometimes', 'boolean'],
            'requirePostReview' => ['sometimes', 'boolean'],
        ];
    }
}
