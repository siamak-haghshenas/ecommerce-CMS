<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\SaleEvents;

use App\Data\SaleEvents\SaleEventData;
use App\Data\SaleEvents\SaleEventItemData;
use App\Enums\PromotionType;
use App\Http\Requests\Concerns\HasMediaFields;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\ProductVariant;
use App\Models\SaleEvent;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating and updating a sale event share the same fields: the event, every variant in it
 * (`items`) and the landing page. Errors on a variant come back as `items.{i}.{field}`.
 */
class SaveSaleEventRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'items' => ['present', 'array', 'max:'.SaleEvent::MAX_ITEMS],
            'items.*' => ['array:variant_id,discount_type,discount_value,max_per_order,starts_at,ends_at'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')],
            'items.*.discount_type' => ['required', Rule::enum(PromotionType::class)],
            'items.*.discount_value' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'items.*.max_per_order' => ['nullable', 'integer', 'min:1', 'max:'.SaleEvent::MAX_PER_ORDER],
            'items.*.starts_at' => ['nullable', 'date'],
            'items.*.ends_at' => ['nullable', 'date'],
            'has_landing_page' => ['required', 'boolean'],
            'slug' => [
                Rule::requiredIf($this->boolean('has_landing_page')),
                'nullable',
                'string',
                'max:120',
                'regex:'.self::SLUG_PATTERN,
                Rule::unique('sale_events', 'slug')->ignore($this->saleEvent()?->id),
            ],
            'landing_title' => ['nullable', 'string', 'max:255'],
            'landing_description' => ['nullable', 'string', 'max:200000'],
            'banner_id' => ['nullable', 'integer', $this->libraryImageRule()],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            'robots_noindex' => ['sometimes', 'boolean'],
            'canonical_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image_id' => ['nullable', 'integer', $this->libraryImageRule()],
        ];
    }

    /**
     * Checks across fields of each variant: the discount suits its price, its dates fall inside
     * the event's, and a deleted variant is only kept when it was already in the event.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $items = (array) $this->input('items', []);
                $variants = ProductVariant::withTrashed()
                    ->whereKey(array_column($items, 'variant_id'))
                    ->get(['id', 'price', 'deleted_at'])
                    ->keyBy('id');
                $kept = $this->saleEvent()?->items()->pluck('product_variant_id')->map(fn (mixed $id): int => (int) $id)->all() ?? [];
                $eventStart = CarbonImmutable::parse($this->string('starts_at')->toString());
                $eventEnd = CarbonImmutable::parse($this->string('ends_at')->toString());

                foreach ($items as $i => $item) {
                    /** @var ProductVariant $variant */
                    $variant = $variants->get((int) $item['variant_id']);
                    $value = (int) $item['discount_value'];

                    if ($variant->trashed() && ! in_array($variant->id, $kept, true)) {
                        $validator->errors()->add("items.{$i}.variant_id", __('admin.sale_events.errors.variant_deleted'));
                    }

                    if ($item['discount_type'] === PromotionType::Percent->value && $value > SaleEvent::MAX_PERCENT) {
                        $validator->errors()->add("items.{$i}.discount_value", __('admin.sale_events.errors.percent_max', ['max' => SaleEvent::MAX_PERCENT]));
                    }

                    if ($item['discount_type'] === PromotionType::Fixed->value && $value >= $variant->price) {
                        $validator->errors()->add("items.{$i}.discount_value", __('admin.sale_events.errors.not_below_price'));
                    }

                    $start = filled($item['starts_at'] ?? null) ? CarbonImmutable::parse((string) $item['starts_at']) : $eventStart;
                    $end = filled($item['ends_at'] ?? null) ? CarbonImmutable::parse((string) $item['ends_at']) : $eventEnd;

                    if ($start->lt($eventStart) || $end->gt($eventEnd) || $end->lte($start)) {
                        $field = filled($item['starts_at'] ?? null) && ($start->lt($eventStart) || $end->lte($start)) ? 'starts_at' : 'ends_at';
                        $validator->errors()->add("items.{$i}.{$field}", __('admin.sale_events.errors.outside_event'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.variant_id' => __('validation.attributes.variant'),
            'items.*.discount_type' => __('validation.attributes.discount_type'),
            'items.*.discount_value' => __('validation.attributes.discount_value'),
            'items.*.max_per_order' => __('validation.attributes.max_per_order'),
            'items.*.starts_at' => __('validation.attributes.starts_at'),
            'items.*.ends_at' => __('validation.attributes.ends_at'),
        ];
    }

    public function toData(): SaleEventData
    {
        $date = fn (mixed $value): ?CarbonImmutable => filled($value) ? CarbonImmutable::parse((string) $value) : null;

        return new SaleEventData(
            name: $this->string('name')->trim()->toString(),
            startsAt: CarbonImmutable::parse($this->string('starts_at')->toString()),
            endsAt: CarbonImmutable::parse($this->string('ends_at')->toString()),
            isActive: $this->boolean('is_active'),
            items: array_values(array_map(fn (array $item): SaleEventItemData => new SaleEventItemData(
                variantId: (int) $item['variant_id'],
                discountType: PromotionType::from((string) $item['discount_type']),
                discountValue: (int) $item['discount_value'],
                startsAt: $date($item['starts_at'] ?? null),
                endsAt: $date($item['ends_at'] ?? null),
                maxPerOrder: ($item['max_per_order'] ?? null) !== null ? (int) $item['max_per_order'] : null,
            ), (array) $this->input('items', []))),
            hasLandingPage: $this->boolean('has_landing_page'),
            slug: $this->optionalText('slug'),
            landingTitle: $this->optionalText('landing_title'),
            landingDescription: RichText::sanitize($this->input('landing_description')),
            bannerId: $this->libraryImageId('banner_id'),
            metaTitle: $this->optionalText('meta_title'),
            metaDescription: $this->optionalText('meta_description'),
            focusKeyphrase: $this->optionalText('focus_keyphrase'),
            robotsNoindex: $this->boolean('robots_noindex'),
            canonicalUrl: $this->optionalText('canonical_url'),
            ogTitle: $this->optionalText('og_title'),
            ogDescription: $this->optionalText('og_description'),
            ogImageId: $this->libraryImageId('og_image_id'),
        );
    }

    private function saleEvent(): ?SaleEvent
    {
        $event = $this->route('saleEvent');

        return $event instanceof SaleEvent ? $event : null;
    }

    private function optionalText(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
