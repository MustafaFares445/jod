<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\UserIntent;
use App\Support\Mobile\SyrianGovernorates;
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
            'preferredCities' => ['sometimes', 'array', 'max:14'],
            'preferredCities.*' => ['string', 'distinct', 'max:100', Rule::in(array_merge(SyrianGovernorates::names(), array_values(array_column(SyrianGovernorates::items(), 'nameEn'))))],
            'remoteHelpEnabled' => ['sometimes', 'boolean'],
        ];
    }
}
