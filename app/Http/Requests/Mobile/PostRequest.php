<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\ContentAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
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
        if ($this->isMethod('patch')) {
            return [
                'type' => ['sometimes', 'string', Rule::in(['volunteer_opportunity', 'donation_campaign', 'help_request', 'service_offer'])],
                'title' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
                'details' => ['sometimes', 'nullable', 'string', 'min:10'],
                'city' => ['sometimes', 'nullable', 'string', 'min:2', 'max:100'],
                'categoryId' => ['sometimes', 'nullable', 'string', 'exists:categories,id'],
                'audience' => ['sometimes', 'string', Rule::enum(ContentAudience::class)],
                'images' => ['prohibited'],
            ];
        }

        $submitting = ! $this->boolean('saveAsDraft');
        $requiredWhenSubmitting = $submitting ? 'required' : 'nullable';

        return [
            'type' => ['required', 'string', Rule::in(['volunteer_opportunity', 'donation_campaign', 'help_request', 'service_offer'])],
            'title' => [$requiredWhenSubmitting, 'string', 'min:4', 'max:255'],
            'details' => [$requiredWhenSubmitting, 'string', 'min:10'],
            'city' => [$requiredWhenSubmitting, 'string', 'min:2', 'max:100'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'audience' => ['sometimes', 'string', Rule::enum(ContentAudience::class)],
            'images' => ['prohibited'],
            'saveAsDraft' => ['sometimes', 'boolean'],
        ];
    }

    public function savesAsDraft(): bool
    {
        return $this->boolean('saveAsDraft');
    }
}
