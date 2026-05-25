<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter.mailbox' => ['sometimes', Rule::in(['all', 'inbox', 'sent'])],
            'filter.status' => ['sometimes', Rule::in(['all', 'unread', 'read', 'sent'])],
            'filter.category' => ['sometimes', Rule::in(['all', 'campaign', 'post', 'account', 'report', 'system', 'donation', 'applicant', 'staff'])],
            'filter.recipientScope' => ['sometimes', Rule::in(['all', 'users', 'organizations'])],
            'filter.date' => ['sometimes', Rule::in(['all', 'today', 'last_7_days'])],
            'sort' => ['sometimes', Rule::in(['sentAt', '-sentAt'])],
        ];
    }
}
