<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Comments;

use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The store's answer to a comment (plain text).
 */
class ReplyToCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reply'))) {
            $this->merge(['reply' => trim(str_replace(["\r\n", "\r"], "\n", $this->string('reply')->toString()))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reply' => ['required', 'string', 'max:'.Comment::BODY_MAX]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reply' => __('validation.attributes.comment_reply')];
    }

    public function reply(): string
    {
        return $this->string('reply')->toString();
    }
}
