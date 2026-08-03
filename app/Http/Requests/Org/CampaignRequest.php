<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

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

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'summary' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'category' => [$isUpdate ? 'sometimes' : 'required', Rule::in(['health', 'education', 'food', 'shelter', 'employment'])],
            'status' => [$isUpdate ? 'prohibited' : 'sometimes', Rule::in(['draft'])],
            'location' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
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
