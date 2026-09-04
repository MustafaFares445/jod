<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExplorationFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contentType' => ['required', 'string', Rule::in(['post', 'campaign'])],
            'contentId' => ['required', 'string'],
            'categoryId' => ['required', 'string', Rule::exists('categories', 'id')->where('status', 'active')],
            'response' => ['required', 'string', Rule::in(['interested', 'not_interested'])],
        ];
    }
}
