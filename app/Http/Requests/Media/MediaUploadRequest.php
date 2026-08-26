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
        $model = (string) $this->route('model');
        $prop = (string) $this->route('prop');

        if ($prop === 'videos') {
            if ($model === 'organization') {
                return [
                    'file' => ['required', 'prohibited'],
                ];
            }

            return [
                'file' => ['required', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
            ];
        }

        return [
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => (string) $this->route('prop') === 'videos' && (string) $this->route('model') === 'organization'
                ? 'Organization videos must be uploaded through the resumable video upload API.'
                : 'The file field is required.',
            'file.prohibited' => 'Organization videos must be uploaded through the resumable video upload API.',
        ];
    }
}
