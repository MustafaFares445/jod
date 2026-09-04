<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\FeedType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationAnalyticsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'feedType' => ['nullable', Rule::enum(FeedType::class)],
            'categoryId' => ['nullable', 'uuid', 'exists:categories,id'],
            'publisherId' => ['nullable', 'uuid'],
            'city' => ['nullable', 'string', 'max:120'],
        ];
    }
}
