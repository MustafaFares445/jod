<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RecommendationConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $weightKeys = [
            'followed_publisher', 'explicit_interest', 'behavioral_interest',
            'same_city', 'intent_match', 'capability_match',
            'freshness', 'urgency', 'group_affinity',
            'repeated_unengaged_view', 'not_interested',
        ];

        $rules = [
            'weights' => ['sometimes', 'array:'.implode(',', $weightKeys)],
            'candidateLimit' => ['sometimes', 'integer', 'min:20', 'max:2000'],
            'popularityCap' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];

        foreach ($weightKeys as $key) {
            $rules['weights.'.$key] = ['sometimes', 'numeric', 'min:-500', 'max:500'];
        }

        return $rules;
    }
}
