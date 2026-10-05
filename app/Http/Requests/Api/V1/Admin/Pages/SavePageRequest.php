<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pages;

use App\Data\Pages\PageData;
use App\Enums\PageStatus;
use App\Http\Requests\Concerns\HasMediaFields;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\Page;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and updating a page share the same fields. The slug stays taken by pages in the trash.
 */
class SavePageRequest extends FormRequest
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
        /** @var Page|null $page */
        $page = $this->route('page');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $this->slugRules('pages', $page?->id),
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:200000'],
            'status' => ['required', Rule::enum(PageStatus::class)],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            // The featured image: an image of the media library (null: none).
            'image_id' => ['nullable', 'integer', $this->libraryImageRule()],
            'robots_noindex' => ['sometimes', 'boolean'],
            'canonical_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image_id' => ['nullable', 'integer', $this->libraryImageRule()],
        ];
    }

    public function toData(): PageData
    {
        return new PageData(
            title: $this->string('title')->trim()->toString(),
            slug: $this->string('slug')->toString(),
            excerpt: $this->optionalText('excerpt'),
            content: RichText::sanitize($this->input('content')),
            status: PageStatus::from($this->string('status')->toString()),
            publishedAt: $this->filled('published_at') ? CarbonImmutable::parse($this->string('published_at')->toString()) : null,
            metaTitle: $this->optionalText('meta_title'),
            metaDescription: $this->optionalText('meta_description'),
            focusKeyphrase: $this->optionalText('focus_keyphrase'),
            imageId: $this->libraryImageId('image_id'),
            robotsNoindex: $this->boolean('robots_noindex'),
            canonicalUrl: $this->optionalText('canonical_url'),
            ogTitle: $this->optionalText('og_title'),
            ogDescription: $this->optionalText('og_description'),
            ogImageId: $this->libraryImageId('og_image_id'),
        );
    }

    private function optionalText(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
