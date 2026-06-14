<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLogs;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'filter.actorUserId' => ['sometimes', 'string', 'exists:users,id'],
            'filter.action' => ['sometimes', 'string', 'max:255'],
            'filter.from' => ['sometimes', 'date'],
            'filter.to' => ['sometimes', 'date', 'after_or_equal:filter.from'],
            'sort' => ['sometimes', 'string', 'in:at,-at'],
        ];
    }
}
