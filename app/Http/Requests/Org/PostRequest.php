<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function rules(): array
    {
        $campaignRelatedTypes = ['campaign_teaser', 'campaign_update', 'campaign_summary'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'type' => ['required', Rule::in(['general', 'job_opportunity', 'campaign_teaser', 'campaign_update', 'campaign_summary'])],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'authorName' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'campaignTitle' => [
                Rule::requiredIf(fn (): bool => in_array((string) $this->input('type'), $campaignRelatedTypes, true)),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
