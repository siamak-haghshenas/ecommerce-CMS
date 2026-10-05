<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Data\Categories\CategoryNode;
use App\Data\Seo\SeoSuggestionContext;
use App\Enums\SeoContentType;
use App\Support\CategoryTree;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The form of a category, brand, ... as currently typed; the item itself may not exist yet.
 */
class SuggestSeoRequest extends FormRequest
{
    /**
     * Asking for suggestions needs the same permission as editing that content.
     */
    public function authorize(): bool
    {
        $type = $this->contentType();

        return $type === null || ($this->user()?->can($type->managePermission()->value) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->contentType();

        return [
            'type' => ['required', Rule::enum(SeoContentType::class)],
            'name' => ['required', 'string', 'max:255'],
            'alternate_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'subject_id' => [
                'nullable',
                'integer',
                ...($type === null ? [] : [Rule::exists((new ($type->modelClass()))->getTable(), 'id')]),
            ],
        ];
    }

    public function toContext(): SeoSuggestionContext
    {
        $type = $this->contentType() ?? SeoContentType::Category;
        $optional = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return new SeoSuggestionContext(
            type: $type,
            name: $this->string('name')->trim()->toString(),
            description: $optional('description'),
            focusKeyphrase: $optional('focus_keyphrase'),
            alternateName: $optional('alternate_name'),
            parentPath: $type->hasParent() && $this->filled('parent_id')
                ? $this->categoryPath($this->integer('parent_id'))
                : [],
            subject: $this->filled('subject_id') ? $type->modelClass()::query()->where($type->constraints())->find($this->integer('subject_id')) : null,
        );
    }

    private function contentType(): ?SeoContentType
    {
        return SeoContentType::tryFrom($this->string('type')->toString());
    }

    /**
     * @return list<string>
     */
    private function categoryPath(int $parentId): array
    {
        $tree = CategoryTree::current();
        $parent = $tree->find($parentId);

        return $parent === null ? [] : [
            ...array_map(fn (CategoryNode $node): string => $node->name, $tree->ancestors($parentId)),
            $parent->name,
        ];
    }
}
