<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Posts;

use App\Data\Posts\PostAutosaveData;
use App\Support\RichText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The editor's unsaved form. Only types and lengths are checked: a half-typed title, a taken slug
 * or a missing category is fine until the admin saves for real.
 */
class AutosavePostRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:200000'],
            'image_id' => ['nullable', 'integer'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            'robots_noindex' => ['sometimes', 'boolean'],
            'canonical_url' => ['nullable', 'string', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image_id' => ['nullable', 'integer'],
        ];
    }

    public function toData(): PostAutosaveData
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->toString() : null;
        $id = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;

        return new PostAutosaveData(
            title: $text('title'),
            slug: $text('slug'),
            excerpt: $text('excerpt'),
            content: RichText::sanitize($this->input('content')),
            imageId: $id('image_id'),
            metaTitle: $text('meta_title'),
            metaDescription: $text('meta_description'),
            focusKeyphrase: $text('focus_keyphrase'),
            robotsNoindex: $this->boolean('robots_noindex'),
            canonicalUrl: $text('canonical_url'),
            ogTitle: $text('og_title'),
            ogDescription: $text('og_description'),
            ogImageId: $id('og_image_id'),
        );
    }
}
