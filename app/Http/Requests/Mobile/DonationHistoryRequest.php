<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\DonationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DonationHistoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'campaignId' => ['sometimes', 'string', 'exists:campaigns,id'],
            'flow' => ['sometimes', 'string', 'in:contributed,received'],
            'status' => ['sometimes', 'string', Rule::enum(DonationStatus::class)],
        ];
    }
}
