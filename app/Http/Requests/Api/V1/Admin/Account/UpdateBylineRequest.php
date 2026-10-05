<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Account;

use App\Data\Account\BylineData;
use App\Http\Requests\Concerns\HasMediaFields;
use App\Http\Requests\Concerns\HasSlugField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The staff member's own byline. An empty name removes the byline (slug, bio and picture too).
 */
class UpdateBylineRequest extends FormRequest
{
    use HasMediaFields, HasSlugField;

    protected function prepareForValidation(): void
    {
        $this->normalizeSlug();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'author_name' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'required_with:author_name', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, Rule::unique('users', 'author_slug')->ignore($this->user()?->getAuthIdentifier())],
            'author_bio' => ['nullable', 'string', 'max:1000'],
            'avatar_id' => ['nullable', 'integer', $this->libraryImageRule()],
        ];
    }

    public function toData(): BylineData
    {
        $name = $this->string('author_name')->trim()->toString();

        if ($name === '') {
            return new BylineData(null, null, null, null);
        }

        $bio = $this->string('author_bio')->trim()->toString();

        return new BylineData(
            name: $name,
            slug: $this->string('slug')->toString(),
            bio: $bio === '' ? null : $bio,
            avatarId: $this->libraryImageId('avatar_id'),
        );
    }
}
