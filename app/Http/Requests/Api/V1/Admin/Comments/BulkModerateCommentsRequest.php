<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Comments;

use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The same status change for several comments (`ids`), all or nothing.
 */
class BulkModerateCommentsRequest extends ModerateCommentRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.Comment::BULK_MAX],
            'ids.*' => ['integer', 'distinct', 'exists:comments,id'],
            ...parent::rules(),
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids')));
    }
}
