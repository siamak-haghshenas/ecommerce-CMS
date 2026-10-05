<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Menus;

use App\Data\Menus\MenuItemData;
use App\Enums\MenuItemType;
use App\Models\MenuItem;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A whole menu: its top items in order, each with its sub-items in order (two levels).
 */
class SaveMenuRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:'.MenuItem::MAX_ITEMS],
            ...$this->itemRules('items.*'),
            'items.*.children' => ['sometimes', 'array', 'max:'.MenuItem::MAX_ITEMS],
            ...$this->itemRules('items.*.children.*'),
            // Two levels only.
            'items.*.children.*.children' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $count = 0;

                foreach ($this->itemsWithKeys() as $key => $item) {
                    $count++;
                    $url = $item['url'] ?? null;

                    if (($item['type'] ?? null) === MenuItemType::Url->value && is_string($url) && ! self::isValidLink($url)) {
                        $validator->errors()->add("{$key}.url", __('validation.custom.menu_url'));
                    }
                }

                if ($count > MenuItem::MAX_ITEMS) {
                    $validator->errors()->add('items', __('validation.max.array', ['attribute' => __('validation.attributes.items'), 'max' => MenuItem::MAX_ITEMS]));
                }
            },
        ];
    }

    /**
     * @return list<MenuItemData>
     */
    public function toData(): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = $this->input('items', []);

        return array_map(fn (array $item): MenuItemData => $this->item($item), $items);
    }

    /**
     * An absolute http/https address, or a storefront path ("/sale"), never "//host" or "javascript:".
     */
    private static function isValidLink(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && preg_match('/\s/', $url) !== 1;
        }

        return preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function itemRules(string $prefix): array
    {
        return [
            "{$prefix}.id" => ['nullable', 'integer'],
            "{$prefix}.type" => ['required', Rule::enum(MenuItemType::class)],
            "{$prefix}.label" => ['nullable', 'string', 'max:120', "required_if:{$prefix}.type,url"],
            "{$prefix}.page_id" => ['nullable', 'integer', "required_if:{$prefix}.type,page", Rule::exists('pages', 'id')],
            "{$prefix}.category_id" => ['nullable', 'integer', "required_if:{$prefix}.type,category", Rule::exists('categories', 'id')],
            "{$prefix}.url" => ['nullable', 'string', 'max:2048', "required_if:{$prefix}.type,url"],
            "{$prefix}.open_in_new_tab" => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Every item and sub-item with its validation key prefix (e.g. "items.0.children.1").
     *
     * @return iterable<string, array<string, mixed>>
     */
    private function itemsWithKeys(): iterable
    {
        foreach ((array) $this->input('items', []) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            yield "items.{$index}" => $item;

            foreach ((array) ($item['children'] ?? []) as $childIndex => $child) {
                if (is_array($child)) {
                    yield "items.{$index}.children.{$childIndex}" => $child;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function item(array $item, bool $withChildren = true): MenuItemData
    {
        $type = MenuItemType::from((string) $item['type']);
        $text = fn (string $key): ?string => is_string($item[$key] ?? null) && trim($item[$key]) !== '' ? trim($item[$key]) : null;
        /** @var list<array<string, mixed>> $children */
        $children = $withChildren ? array_values((array) ($item['children'] ?? [])) : [];

        return new MenuItemData(
            id: isset($item['id']) ? (int) $item['id'] : null,
            type: $type,
            label: $text('label'),
            pageId: $type === MenuItemType::Page ? (int) $item['page_id'] : null,
            categoryId: $type === MenuItemType::Category ? (int) $item['category_id'] : null,
            url: $type === MenuItemType::Url ? $text('url') : null,
            openInNewTab: (bool) ($item['open_in_new_tab'] ?? false),
            children: array_map(fn (array $child): MenuItemData => $this->item($child, false), $children),
        );
    }
}
