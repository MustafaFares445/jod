<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Support\SyrianGovernorates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('campaign') !== null;
        $allowsStatusUpdate = $isUpdate && $this->isMethod('patch');

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'summary' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'categoryId' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('target', 'campaign')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
            'status' => $isUpdate
                ? [$allowsStatusUpdate ? 'sometimes' : 'prohibited', Rule::in(['draft', 'active', 'closed'])]
                : ['sometimes', Rule::in(['draft', 'active'])],
            'closedReason' => $allowsStatusUpdate
                ? [Rule::requiredIf(fn (): bool => $this->input('status') === 'closed'), 'nullable', 'string', 'min:8', 'max:500']
                : ['prohibited'],
            'location' => [$isUpdate ? 'sometimes' : 'required', 'string', Rule::in(SyrianGovernorates::ALL)],
            'goalAmount' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'beneficiariesCount' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:0'],
            'startDate' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'endDate' => [
                $isUpdate ? 'sometimes' : 'required',
                'date',
                Rule::when($this->filled('startDate'), ['after_or_equal:startDate']),
            ],
        ];
    }
}
