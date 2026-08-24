<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use App\Enums\HelpOfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HelpOfferHistoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::enum(HelpOfferStatus::class)],
            'postId' => ['sometimes', 'string', 'exists:posts,id'],
            'flow' => ['sometimes', 'string', Rule::in(['made', 'received'])],
        ];
    }
}
