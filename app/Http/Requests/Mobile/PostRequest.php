<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{type: list<mixed>, title: list<mixed>, details: list<mixed>, city: list<mixed>, categoryId: list<mixed>, images: list<mixed>, "images.*"?: list<mixed>, saveAsDraft?: list<mixed>}
     */
    public function rules(): array
    {
        if ($this->isMethod('patch')) {
            return [
                'type' => ['sometimes', 'string', Rule::in(['volunteer_opportunity', 'donation_campaign', 'help_request'])],
                'title' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
                'details' => ['sometimes', 'nullable', 'string', 'min:10'],
                'city' => ['sometimes', 'nullable', 'string', 'min:2', 'max:100'],
                'categoryId' => ['sometimes', 'nullable', 'string', 'exists:categories,id'],
                'images' => ['prohibited'],
            ];
        }

        $submitting = ! $this->boolean('saveAsDraft');
        $requiredWhenSubmitting = $submitting ? 'required' : 'nullable';

        return [
            'type' => ['required', 'string', Rule::in(['volunteer_opportunity', 'donation_campaign', 'help_request'])],
            'title' => [$requiredWhenSubmitting, 'string', 'min:4', 'max:255'],
            'details' => [$requiredWhenSubmitting, 'string', 'min:10'],
            'city' => [$requiredWhenSubmitting, 'string', 'min:2', 'max:100'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'images' => [
                'sometimes',
                'array',
                'max:5',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    foreach ($value as $image) {
                        if (! $image instanceof UploadedFile) {
                            $fail('Every image must be an uploaded image file.');

                            return;
                        }
                    }
                },
            ],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'saveAsDraft' => ['sometimes', 'boolean'],
        ];
    }

    public function savesAsDraft(): bool
    {
        return $this->boolean('saveAsDraft');
    }
}
