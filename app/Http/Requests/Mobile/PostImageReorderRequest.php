<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class PostImageReorderRequest extends FormRequest
{
    /**
     * @return array{imageIds: list<string>, "imageIds.*": list<string>}
     */
    public function rules(): array
    {
        return [
            'imageIds' => ['required', 'array', 'max:5'],
            'imageIds.*' => ['required', 'string', 'distinct'],
        ];
    }
}
