<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\DonationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DonationHistoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $status = $this->query('status');
        $flow = $this->query('flow');

        $normalizedStatus = is_string($status) ? strtolower(trim($status)) : $status;
        $normalizedFlow = is_string($flow) ? strtolower(trim($flow)) : $flow;

        $this->merge([
            'status' => $normalizedStatus === 'all' || $normalizedStatus === '' ? null : $normalizedStatus,
            'flow' => $normalizedFlow === '' ? null : $normalizedFlow,
        ]);
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'campaignId' => ['sometimes', 'string', 'exists:campaigns,id'],
            'flow' => ['sometimes', 'nullable', 'string', 'in:contributed,received'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(DonationStatus::class)],
        ];
    }
}
