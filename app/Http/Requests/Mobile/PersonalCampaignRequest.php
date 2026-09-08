<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\ContentAudience;
use App\Support\Mobile\SyrianGovernorates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $partial = $this->isMethod('patch');
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'min:4', 'max:255'],
            'summary' => [$required, 'string', 'min:10', 'max:10000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'categoryId' => [$required, 'string', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'audience' => ['sometimes', 'string', Rule::enum(ContentAudience::class)],
            'location' => [$required, 'string', Rule::in(SyrianGovernorates::names())],
            'goalAmount' => [$required, 'numeric', 'gt:0', 'max:999999999.99'],
            'beneficiariesCount' => ['sometimes', 'integer', 'min:0'],
            'startDate' => ['sometimes', 'nullable', 'date'],
            'endDate' => ['sometimes', 'nullable', 'date', 'after_or_equal:startDate'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
