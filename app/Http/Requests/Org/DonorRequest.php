<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;

class DonorRequest extends FormRequest
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
            'amountOrType' => [$required, 'string', 'max:255'],
            // The dashboard does not send donatedAt; default it server-side on create.
            'donatedAt' => ['sometimes', 'nullable', 'date'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paymentMethod' => ['sometimes', 'nullable', 'string', 'max:255'],
            'campaignRef' => ['sometimes', 'nullable', 'string', 'max:255'],
            'assignedTo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'internalNotes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
