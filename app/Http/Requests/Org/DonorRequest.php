<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;

class DonorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'campaignId' => ['nullable', 'integer', 'exists:campaigns,id'],
            'campaignTitle' => ['required', 'string', 'max:255'],
            'amountOrType' => ['required', 'string', 'max:255'],
            'donatedAt' => ['required', 'date'],
            'city' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'paymentMethod' => ['nullable', 'string', 'max:255'],
            'campaignRef' => ['nullable', 'string', 'max:255'],
            'assignedTo' => ['nullable', 'string', 'max:255'],
            'internalNotes' => ['nullable', 'string'],
        ];
    }
}
