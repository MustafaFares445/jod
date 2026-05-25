<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'category' => ['required', Rule::in(['health', 'education', 'food', 'shelter', 'employment'])],
            'status' => ['required', Rule::in(['draft', 'active', 'closed'])],
            'location' => ['required', 'string', 'max:255'],
            'goalAmount' => ['required', 'numeric', 'min:0'],
            'beneficiariesCount' => ['required', 'integer', 'min:0'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ];
    }
}
