<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renaming a tag (`name`) or merging it into another (`into_tag_id`).
 */
class TagRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tag = $this->route('tag');
        $tagId = $tag instanceof Tag ? $tag->id : 0;

        return $this->routeIs('*.merge')
            ? ['into_tag_id' => ['required', 'integer', Rule::exists('tags', 'id'), Rule::notIn([$tagId])]]
            : ['name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')->ignore($tagId)]];
    }
}
