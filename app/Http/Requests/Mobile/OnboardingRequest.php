<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\UserIntent;
use App\Support\Mobile\SyrianGovernorates;
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
            'preferredCities' => ['sometimes', 'array', 'max:14'],
            'preferredCities.*' => ['string', 'distinct', 'max:100', Rule::in(array_merge(SyrianGovernorates::names(), array_values(array_column(SyrianGovernorates::items(), 'nameEn'))))],
            'remoteHelpEnabled' => ['sometimes', 'boolean'],
        ];
    }
}
