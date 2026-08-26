<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PostSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $post = $this->route('post');

                if (! $post) {
                    return;
                }

                if (! in_array($post->type, ['volunteer_opportunity', 'donation_campaign', 'help_request', 'service_offer'], true)) {
                    $validator->errors()->add('type', 'The selected type is invalid.');
                }

                if (! is_string($post->title) || mb_strlen($post->title) < 4) {
                    $validator->errors()->add('title', 'The title field must be at least 4 characters.');
                }

                if (! is_string($post->content) || mb_strlen($post->content) < 10) {
                    $validator->errors()->add('details', 'The details field must be at least 10 characters.');
                }

                if (! is_string($post->location) || mb_strlen($post->location) < 2 || mb_strlen($post->location) > 100) {
                    $validator->errors()->add('city', 'The city field must be between 2 and 100 characters.');
                }
            },
        ];
    }
}
