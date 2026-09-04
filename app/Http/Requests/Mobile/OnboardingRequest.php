<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\AvailabilityStatus;
use App\Enums\UserIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'skipped' => ['sometimes', 'boolean'],
            'intent' => ['sometimes', 'nullable', Rule::enum(UserIntent::class)],
            'categoryIds' => ['sometimes', 'nullable', 'array', 'max:20'],
            'categoryIds.*' => [
                'string',
                'distinct',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'capabilityIds' => ['sometimes', 'nullable', 'array', 'max:20'],
            'capabilityIds.*' => [
                'string',
                'distinct',
                Rule::exists('capabilities', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'preferredCity' => ['sometimes', 'nullable', 'string', 'max:100'],
            'preferredGovernorate' => ['sometimes', 'nullable', 'string', 'max:100'],
            'preferredRadiusKm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'remoteHelpEnabled' => ['sometimes', 'boolean'],
            'availabilityStatus' => ['sometimes', 'nullable', Rule::enum(AvailabilityStatus::class)],
        ];
    }
}
