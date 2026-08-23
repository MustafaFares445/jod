<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Enums\NotificationEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['required', Rule::in(['campaign', 'post', 'account', 'report', 'system', 'donation', 'applicant', 'staff'])],
            'eventType' => ['nullable', Rule::enum(NotificationEventType::class)],
            'recipientScope' => ['nullable', Rule::in(['all', 'users', 'organizations'])],
            'recipientLabel' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(['normal', 'high'])],
            'referenceLabel' => ['nullable', 'string', 'max:255'],
            'referencePath' => ['nullable', 'string', 'max:255'],
        ];
    }
}
