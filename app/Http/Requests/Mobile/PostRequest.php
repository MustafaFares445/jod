<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\ContentAudience;
use App\Support\Mobile\SyrianGovernorates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $types = ['volunteer_opportunity', 'help_request', 'service_offer', 'awareness', 'poll', 'donation_campaign'];
        $common = [
            'groupId' => ['sometimes', 'nullable', 'string', 'exists:groups,id'],
            'campaignId' => ['sometimes', 'nullable', 'string', 'exists:campaigns,id'],
            'pollQuestion' => ['sometimes', 'nullable', 'string', 'min:3', 'max:500'],
            'pollOptions' => ['sometimes', 'array', 'min:2', 'max:10'],
            'pollOptions.*' => ['required', 'string', 'min:1', 'max:300', 'distinct'],
            'allowsMultipleChoices' => ['sometimes', 'boolean'],
            'pollEndsAt' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];

        if ($this->isMethod('patch')) {
            return array_merge([
                'type' => ['sometimes', 'string', Rule::in($types)],
                'title' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
                'details' => ['sometimes', 'nullable', 'string', 'min:10'],
                'cityId' => ['sometimes', 'nullable', 'string', Rule::in(SyrianGovernorates::ids())],
                'city' => ['sometimes', 'nullable', 'string', Rule::in(SyrianGovernorates::names())],
                'categoryId' => ['sometimes', 'nullable', 'string', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active'))],
                'audience' => ['sometimes', 'string', Rule::enum(ContentAudience::class)],
                'images' => ['prohibited'],
            ], $common);
        }

        $submitting = ! $this->boolean('saveAsDraft');
        $requiredWhenSubmitting = $submitting ? 'required' : 'nullable';
        return array_merge([
            'type' => ['required', 'string', Rule::in($types)],
            'title' => [$requiredWhenSubmitting, 'string', 'min:4', 'max:255'],
            'details' => [$requiredWhenSubmitting, 'string', 'min:10'],
            'cityId' => [$submitting ? 'required_without:city' : 'nullable', 'nullable', 'string', Rule::in(SyrianGovernorates::ids())],
            'city' => [$submitting ? 'required_without:cityId' : 'nullable', 'nullable', 'string', Rule::in(SyrianGovernorates::names())],
            'categoryId' => [$requiredWhenSubmitting, 'string', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'audience' => ['sometimes', 'string', Rule::enum(ContentAudience::class)],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'saveAsDraft' => ['sometimes', 'boolean'],
        ], $common);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = (string) $this->input('type');
            $groupId = $this->input('groupId');
            if (! filled($groupId) && in_array($type, ['awareness', 'poll'], true)) {
                $validator->errors()->add('type', 'This post type is only available inside a volunteer group.');
            }
            if ($type === 'donation_campaign' && ! filled($groupId) && ! filled($this->input('campaignId'))) {
                $validator->errors()->add('campaignId', 'A donation campaign post must be linked to a campaign.');
            }
            if ($type === 'poll') {
                if (! filled($this->input('pollQuestion'))) $validator->errors()->add('pollQuestion', 'Poll question is required.');
                if (count((array) $this->input('pollOptions', [])) < 2) $validator->errors()->add('pollOptions', 'At least two poll options are required.');
            }
        }];
    }

    public function savesAsDraft(): bool { return $this->boolean('saveAsDraft'); }
}
