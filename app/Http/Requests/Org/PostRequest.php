<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campaignRelatedTypes = ['campaign_teaser', 'campaign_update', 'campaign_summary'];
        $isUpdate = $this->route('post') !== null;
        $organizationId = $this->user()?->organization_id;

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'summary' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(['general', 'job_opportunity', 'campaign_teaser', 'campaign_update', 'campaign_summary'])],
            'status' => [$isUpdate ? 'prohibited' : 'sometimes', Rule::in(['draft', 'published'])],
            'location' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'campaignTitle' => [
                Rule::requiredIf(fn (): bool => in_array((string) $this->input('type'), $campaignRelatedTypes, true)),
                'nullable',
                'string',
                'max:255',
                Rule::exists('campaigns', 'title')->where('organization_id', $organizationId),
            ],
        ];
    }
}
