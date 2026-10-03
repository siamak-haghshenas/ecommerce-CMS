<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Tag;
use App\Support\Seo\SeoText;

final class ResolveTagsAction
{
    /**
     * Tag ids for the typed names: existing tags are matched ignoring case and Arabic/Persian
     * letter differences («هديه» = «هدیه»); missing ones are created with a Latin slug.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    public function handle(array $names): array
    {
        $byKey = [];

        foreach ($names as $name) {
            $byKey[$this->key($name)] ??= $name;
        }

        if ($byKey === []) {
            return [];
        }

        $existing = Tag::query()->get()->keyBy(fn (Tag $tag): string => $this->key($tag->name));
        $ids = [];

        foreach ($byKey as $key => $name) {
            $ids[] = $existing->has($key)
                ? $existing[$key]->id
                : Tag::query()->create(['name' => $name, 'slug' => $this->uniqueSlug($name)])->id;
        }

        return $ids;
    }

    public function key(string $name): string
    {
        return implode(' ', SeoText::words($name));
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = SeoText::slugify($name) ?: 'tag';
        $slug = $base;

        for ($suffix = 2; Tag::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
