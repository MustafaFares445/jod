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
            'intent' => ['required', Rule::enum(UserIntent::class)],
            'categoryIds' => ['required', 'array', 'min:1', 'max:20'],
            'categoryIds.*' => [
                'string',
                'distinct',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'capabilityIds' => ['sometimes', 'array', 'max:20'],
            'capabilityIds.*' => [
                'string',
                'distinct',
                Rule::exists('capabilities', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'preferredCity' => ['nullable', 'string', 'max:100'],
            'preferredGovernorate' => ['nullable', 'string', 'max:100'],
            'preferredRadiusKm' => ['nullable', 'integer', 'min:1', 'max:500'],
            'remoteHelpEnabled' => ['sometimes', 'boolean'],
            'availabilityStatus' => ['nullable', Rule::enum(AvailabilityStatus::class)],
        ];
    }
}
