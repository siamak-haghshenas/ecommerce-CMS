<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Comments;

use App\Data\Comments\ModerationData;
use App\Enums\CommentStatus;
use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A status change: approved, rejected or spam (never back to pending), with an optional reason
 * for staff, only when rejecting.
 */
class ModerateCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->string('reason')->toString()) ?: null]);
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
            'status' => ['required', Rule::enum(CommentStatus::class)->only(CommentStatus::moderated())],
            'reason' => ['nullable', 'string', 'max:'.Comment::REASON_MAX, 'prohibited_unless:status,'.CommentStatus::Rejected->value],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.prohibited_unless' => __('comments.errors.reason_only_when_rejected')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => __('validation.attributes.rejection_reason')];
    }

    public function toData(): ModerationData
    {
        return new ModerationData(
            CommentStatus::from($this->string('status')->toString()),
            $this->filled('reason') ? $this->string('reason')->toString() : null,
        );
    }
}
