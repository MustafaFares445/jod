<?php

declare(strict_types=1);

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class DashboardPushDeviceRequest extends FormRequest
{
    /**
     * @return array{fcmToken: list<mixed>, deviceId: list<mixed>, appVersion: list<mixed>}
     */
    public function rules(): array
    {
        return [
            'fcmToken' => ['required', 'string', 'max:512'],
            'deviceId' => ['nullable', 'string', 'max:255'],
            'appVersion' => ['nullable', 'string', 'max:64'],
        ];
    }
}
