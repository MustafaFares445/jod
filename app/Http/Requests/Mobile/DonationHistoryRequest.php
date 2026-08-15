<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class DonationHistoryRequest extends FormRequest
{
    /**
     * @return array{
     *     page: list<mixed>,
     *     perPage: list<mixed>,
     *     campaignId: list<mixed>
     * }
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'campaignId' => ['sometimes', 'string', 'exists:campaigns,id'],
        ];
    }
}
