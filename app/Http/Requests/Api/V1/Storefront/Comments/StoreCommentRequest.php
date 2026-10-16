<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Comments;

use App\Data\Comments\CommentData;
use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A customer's comment: a display name and plain text. Nothing else is read, so a `status` (or any
 * other field) sent along changes nothing.
 */
class StoreCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['display_name', 'body'] as $key) {
            if (is_string($this->input($key))) {
                // Plain text: line breaks kept (at most one empty line), other control characters dropped.
                $text = preg_replace(["/\r\n?/", '/[^\P{C}\n]/u', "/\n{3,}/"], ["\n", '', "\n\n"], $this->string($key)->toString());
                $this->merge([$key => trim((string) $text)]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:'.Comment::DISPLAY_NAME_MAX, 'not_regex:/\n/'],
            'body' => ['required', 'string', 'max:'.Comment::BODY_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'display_name' => __('validation.attributes.comment_display_name'),
            'body' => __('validation.attributes.comment_body'),
        ];
    }

    public function toData(): CommentData
    {
        return new CommentData($this->string('display_name')->toString(), $this->string('body')->toString());
    }
}
