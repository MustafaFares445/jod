<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DonationRequest extends FormRequest
{
    /**
     * @return array{
     *     amount: list<mixed>,
     *     paymentMethod: list<mixed>,
     *     phone: list<mixed>,
     *     city: list<mixed>
     * }
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
            'paymentMethod' => ['required', 'string', Rule::in(['credit_card', 'bank_transfer', 'cash', 'other'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
        ];
    }
}
