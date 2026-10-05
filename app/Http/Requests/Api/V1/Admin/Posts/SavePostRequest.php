<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Posts;

use App\Data\Posts\PostData;
use App\Enums\CategoryType;
use App\Enums\PostStatus;
use App\Http\Requests\Concerns\HasMediaFields;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\Post;
use App\Models\User;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating and updating a post share the same fields. The slug stays taken by posts in the trash.
 */
class SavePostRequest extends FormRequest
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
        /** @var Post|null $post */
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $this->slugRules('posts', $post?->id),
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:200000'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'author_id' => ['nullable', 'integer'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'category_ids' => ['array', 'max:10'],
            // Blog categories only (product categories share the table).
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('type', CategoryType::Blog->value)],
            'primary_category_id' => ['nullable', 'integer'],
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:60'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            'image_id' => ['nullable', 'integer', $this->libraryImageRule()],
            'robots_noindex' => ['sometimes', 'boolean'],
            'canonical_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image_id' => ['nullable', 'integer', $this->libraryImageRule()],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('primary_category_id') && ! in_array($this->integer('primary_category_id'), $this->ints('category_ids'), true)) {
                    $validator->errors()->add('primary_category_id', __('admin.posts.errors.primary_not_in_categories'));
                }

                if ($this->filled('author_id') && ! User::query()->staff()->whereKey($this->integer('author_id'))->exists()) {
                    $validator->errors()->add('author_id', __('admin.posts.errors.author_not_staff'));
                }
            },
        ];
    }

    public function toData(): PostData
    {
        $categoryIds = $this->ints('category_ids');

        return new PostData(
            title: $this->string('title')->trim()->toString(),
            slug: $this->string('slug')->toString(),
            excerpt: $this->optionalText('excerpt'),
            content: RichText::sanitize($this->input('content')),
            status: PostStatus::from($this->string('status')->toString()),
            publishedAt: $this->filled('published_at') ? CarbonImmutable::parse($this->string('published_at')->toString()) : null,
            authorId: $this->filled('author_id') ? $this->integer('author_id') : null,
            commentsEnabled: $this->boolean('comments_enabled', true),
            categoryIds: $categoryIds,
            // The first category is primary unless another one is chosen.
            primaryCategoryId: $this->filled('primary_category_id') ? $this->integer('primary_category_id') : ($categoryIds[0] ?? null),
            tags: array_values(array_filter(array_map(
                fn (mixed $name): string => trim(preg_replace('/\s+/u', ' ', (string) $name) ?? ''),
                (array) $this->input('tags', []),
            ))),
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

    /**
     * @return list<int>
     */
    private function ints(string $key): array
    {
        return array_values(array_map(intval(...), (array) $this->input($key, [])));
    }
}
