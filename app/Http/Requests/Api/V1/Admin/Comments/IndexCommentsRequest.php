<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Comments;

use App\Data\Comments\CommentFilters;
use App\Enums\CommentableType;
use App\Enums\CommentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The moderation list: `status` (default `pending`; `all` for every status), `q`, `type` (`post`
 * or `product`) and `item_id` (one post or product, with `type`).
 */
class IndexCommentsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', ...array_column(CommentStatus::cases(), 'value')])],
            'type' => ['nullable', Rule::enum(CommentableType::class)],
            'item_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('q') ? $this->string('q')->trim()->toString() : null;
    }

    public function type(): ?CommentableType
    {
        return $this->filled('type') ? CommentableType::from($this->string('type')->toString()) : null;
    }

    public function itemId(): ?int
    {
        return $this->filled('item_id') && $this->type() !== null ? $this->integer('item_id') : null;
    }

    public function toFilters(): CommentFilters
    {
        $status = $this->filled('status') ? $this->string('status')->toString() : CommentStatus::Pending->value;

        return new CommentFilters(
            search: $this->search(),
            status: $status === 'all' ? null : CommentStatus::from($status),
            type: $this->type(),
            itemId: $this->itemId(),
            perPage: $this->filled('per_page') ? $this->integer('per_page') : 20,
        );
    }
}
