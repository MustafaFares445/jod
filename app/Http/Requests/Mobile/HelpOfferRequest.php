<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HelpOfferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['financial', 'supplies', 'service', 'transportation', 'medicine', 'food', 'other'])],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999.99', 'required_if:type,financial'],
            'description' => ['nullable', 'string', 'max:3000'],
            'contactMethod' => ['required', 'string', Rule::in(['phone', 'whatsapp', 'email', 'other'])],
            'contactValue' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
