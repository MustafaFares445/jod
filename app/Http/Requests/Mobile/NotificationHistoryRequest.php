<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationHistoryRequest extends FormRequest
{
    /**
     * @return array{
     *     page: list<string>,
     *     perPage: list<string>,
     *     status: list<mixed>,
     *     category: list<mixed>,
     *     priority: list<mixed>
     * }
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['unread', 'read'])],
            'category' => ['sometimes', 'string', Rule::in([
                'campaign',
                'post',
                'account',
                'report',
                'system',
                'donation',
                'help',
                'applicant',
                'staff',
                'badge',
            ])],
            'priority' => ['sometimes', 'string', Rule::in(['normal', 'high'])],
        ];
    }
}
