<?php

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FirebasePushTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'fcmToken' => ['required', 'string', 'max:512'],
            'platform' => ['sometimes', 'string', Rule::in(['web', 'ios', 'android', 'mobile'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:2000'],
        ];
    }
}
