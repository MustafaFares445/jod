<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class PostViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'durationSeconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'visiblePercent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
