<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contentType' => ['required', Rule::in(['post', 'campaign', 'media', 'article'])],
            'contentId' => ['required', 'string'],
            'action' => ['required', Rule::in(['interested', 'not_interested'])],
        ];
    }
}
