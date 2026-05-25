<?php

declare(strict_types=1);

namespace App\Http\Requests\Badges;

use Illuminate\Foundation\Http\FormRequest;

class BadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:badges,name,' . ($this->badge->id ?? 'NULL')],
            'description' => ['required', 'string', 'max:1000'],
            'criteria' => ['required', 'string', 'max:1000'],
            'iconName' => ['required', 'string', 'max:255'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
