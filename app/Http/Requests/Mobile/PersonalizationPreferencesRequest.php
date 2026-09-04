<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\UserIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalizationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'intent' => ['sometimes', Rule::enum(UserIntent::class)],
            'preferredCity' => ['sometimes', 'nullable', 'string', 'max:100'],
            'remoteHelpEnabled' => ['sometimes', 'boolean'],
        ];
    }
}
