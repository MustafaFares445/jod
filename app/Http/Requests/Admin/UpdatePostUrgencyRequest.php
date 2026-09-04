<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PostUrgency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostUrgencyRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'urgency' => ['required', Rule::enum(PostUrgency::class)],
            'reason' => [Rule::requiredIf($this->input('urgency') === PostUrgency::Critical->value), 'nullable', 'string', 'max:1000'],
        ];
    }
}
