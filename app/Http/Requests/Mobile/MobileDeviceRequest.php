<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileDeviceRequest extends FormRequest
{
    /**
     * @return array{pushToken: list<mixed>, pushTargetType: list<mixed>, platform: list<mixed>, deviceId: list<mixed>, appVersion: list<mixed>}
     */
    public function rules(): array
    {
        return [
            'pushToken' => ['required', 'string', 'max:512'],
            'pushTargetType' => ['sometimes', 'string', Rule::in(['token', 'fid'])],
            'platform' => ['required', 'string', Rule::in(['ios', 'android'])],
            'deviceId' => ['nullable', 'string', 'max:255'],
            'appVersion' => ['nullable', 'string', 'max:64'],
        ];
    }
}
