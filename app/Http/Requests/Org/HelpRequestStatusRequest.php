<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Enums\HelpRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HelpRequestStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([
            HelpRequestStatus::Open->value,
            HelpRequestStatus::InProgress->value,
            HelpRequestStatus::Fulfilled->value,
            HelpRequestStatus::PartiallyFulfilled->value,
            HelpRequestStatus::NotFulfilled->value,
        ])]];
    }
}
