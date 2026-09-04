<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\HelpRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostFulfillmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['status' => ['required', Rule::enum(HelpRequestStatus::class)]]; }
}
