<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;

class ApplicantRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'campaignId' => ['sometimes', 'nullable', 'string', 'exists:campaigns,id'],
            'campaignTitle' => [$required, 'string', 'max:255'],
            // Dashboard uses the shared donor/applicant model and sends amountOrType.
            'amountOrType' => [$required, 'string', 'max:255'],
            // These are backend lifecycle fields and are optional from the dashboard.
            'applicantStatus' => ['sometimes', 'nullable', 'string', 'max:100'],
            'appliedAt' => ['sometimes', 'nullable', 'date'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'campaignRef' => ['sometimes', 'nullable', 'string', 'max:255'],
            'assignedTo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'internalNotes' => ['sometimes', 'nullable', 'string'],
            'requestType' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
