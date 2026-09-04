<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Enums\ContentAudience;
use App\Enums\PostUrgency;
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
        $allowedTypes = [
            'general', 'job_opportunity', 'campaign_teaser', 'campaign_update', 'campaign_summary',
            'service_offer', 'volunteer_opportunity', 'awareness', 'help_request',
        ];
        $isUpdate = $this->route('post') !== null;
        $organizationId = $this->user()?->organization_id;
        $type = (string) ($this->input('type') ?: $this->route('post')?->type);
        $isHelpRequest = $type === 'help_request';

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'summary' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'type' => [$isUpdate ? 'sometimes' : 'required', Rule::in($allowedTypes)],
            'categoryId' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at')),
            ],
            'audience' => ['sometimes', 'string', Rule::enum(ContentAudience::class)],
            'status' => [$isUpdate ? 'prohibited' : 'sometimes', Rule::in(['draft', 'published'])],
            'location' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'campaignTitle' => [
                Rule::requiredIf(fn (): bool => in_array($type, $campaignRelatedTypes, true)),
                'nullable', 'string', 'max:255',
                Rule::exists('campaigns', 'title')->where('organization_id', $organizationId),
            ],
            'urgency' => [$isHelpRequest ? 'sometimes' : 'prohibited', Rule::in([
                PostUrgency::Normal->value,
                PostUrgency::Important->value,
                PostUrgency::Urgent->value,
            ])],
            'urgencyReason' => [
                $isHelpRequest ? Rule::requiredIf(fn (): bool => $this->input('urgency') === PostUrgency::Urgent->value) : 'prohibited',
                'nullable', 'string', 'min:8', 'max:1000',
            ],
            'expiresAt' => [$isHelpRequest ? 'sometimes' : 'prohibited', 'nullable', 'date', 'after:now'],
            'requiredCapabilityIds' => [$isHelpRequest ? 'sometimes' : 'prohibited', 'array', 'max:20'],
            'requiredCapabilityIds.*' => ['string', 'distinct', Rule::exists('capabilities', 'id')->where('status', 'active')],
        ];
    }
}
