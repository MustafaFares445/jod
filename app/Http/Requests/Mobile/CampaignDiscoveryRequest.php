<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class CampaignDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active'],
            'category' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'organizationId' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:updatedAt,-updatedAt,progress,-progress'],
            'sortBy' => ['nullable', 'string', 'in:updated_oldest,progress_highest,progress_lowest'],
        ];
    }
}
