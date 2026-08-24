<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $prop = (string) $this->route('prop');

        if ($prop === 'videos') {
            return [
                'file' => ['required', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
            ];
        }

        return [
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
