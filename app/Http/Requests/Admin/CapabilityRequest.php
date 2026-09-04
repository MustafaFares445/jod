<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CapabilityRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $capabilityId = $this->route('capability')?->getKey();
        $creating = $this->isMethod('post');
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'slug' => [$creating ? 'required' : 'sometimes', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('capabilities', 'slug')->ignore($capabilityId)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'sortOrder' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
