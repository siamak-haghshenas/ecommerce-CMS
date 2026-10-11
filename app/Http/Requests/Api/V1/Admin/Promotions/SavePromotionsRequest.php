<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Promotions;

use App\Data\Promotions\PromotionRowData;
use App\Data\Promotions\SavePromotionsData;
use App\Enums\PromotionType;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One save of the promotions page: new rows (no id) and changed ones (`promotions`), and the ids
 * of removed ones (`deleted`). All or nothing; errors come back as `promotions.{i}.{field}`.
 */
class SavePromotionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'promotions' => ['present', 'array', 'max:'.Promotion::MAX_PER_SAVE],
            'promotions.*' => ['array:id,variant_id,discount_type,discount_value,max_per_order,quantity_limit,starts_at,ends_at'],
            'promotions.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('promotions', 'id')],
            'promotions.*.variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'promotions.*.discount_type' => ['required', Rule::enum(PromotionType::class)],
            'promotions.*.discount_value' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'promotions.*.max_per_order' => ['nullable', 'integer', 'min:1', 'max:'.Promotion::MAX_PER_ORDER],
            'promotions.*.quantity_limit' => ['nullable', 'integer', 'min:1', 'max:'.Promotion::MAX_QUANTITY],
            'promotions.*.starts_at' => ['required', 'date'],
            'promotions.*.ends_at' => ['required', 'date', 'after:promotions.*.starts_at'],
            'deleted' => ['present', 'array', 'max:'.Promotion::MAX_PER_SAVE],
            'deleted.*' => ['integer', 'distinct', Rule::exists('promotions', 'id')],
        ];
    }

    /**
     * Checks that need the database: the discount against the variant's price, no new row on a
     * deleted variant, and no two rows of a variant overlapping in time (saved rows included).
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $rows = $this->rowsWithVariants();
                $variants = ProductVariant::withTrashed()
                    ->whereKey(array_column($rows, 'variant_id'))
                    ->get(['id', 'price', 'deleted_at'])
                    ->keyBy('id');

                foreach ($rows as $i => $row) {
                    /** @var ProductVariant $variant */
                    $variant = $variants->get($row['variant_id']);
                    $value = (int) $row['discount_value'];

                    if ($row['id'] === null && $variant->trashed()) {
                        $validator->errors()->add("promotions.{$i}.variant_id", __('admin.promotions.errors.variant_deleted'));
                    }

                    if ($row['discount_type'] === PromotionType::Percent->value && $value > Promotion::MAX_PERCENT) {
                        $validator->errors()->add("promotions.{$i}.discount_value", __('admin.promotions.errors.percent_max', ['max' => Promotion::MAX_PERCENT]));
                    }

                    if ($row['discount_type'] === PromotionType::Fixed->value && $value >= $variant->price) {
                        $validator->errors()->add("promotions.{$i}.discount_value", __('admin.promotions.errors.not_below_price'));
                    }
                }

                foreach ($this->overlapping($rows) as $i) {
                    $validator->errors()->add("promotions.{$i}.starts_at", __('admin.promotions.errors.overlap'));
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
            'promotions.*.variant_id' => __('validation.attributes.variant'),
            'promotions.*.discount_type' => __('validation.attributes.discount_type'),
            'promotions.*.discount_value' => __('validation.attributes.discount_value'),
            'promotions.*.max_per_order' => __('validation.attributes.max_per_order'),
            'promotions.*.quantity_limit' => __('validation.attributes.quantity_limit'),
            'promotions.*.starts_at' => __('validation.attributes.starts_at'),
            'promotions.*.ends_at' => __('validation.attributes.ends_at'),
        ];
    }

    public function toData(): SavePromotionsData
    {
        return new SavePromotionsData(
            rows: array_map(fn (array $row): PromotionRowData => new PromotionRowData(
                id: $row['id'],
                variantId: $row['variant_id'],
                discountType: PromotionType::from((string) $row['discount_type']),
                discountValue: (int) $row['discount_value'],
                maxPerOrder: ($row['max_per_order'] ?? null) === null ? null : (int) $row['max_per_order'],
                quantityLimit: ($row['quantity_limit'] ?? null) === null ? null : (int) $row['quantity_limit'],
                startsAt: CarbonImmutable::parse((string) $row['starts_at']),
                endsAt: CarbonImmutable::parse((string) $row['ends_at']),
            ), $this->rowsWithVariants()),
            deletedIds: array_values(array_map(intval(...), (array) $this->input('deleted', []))),
        );
    }

    /**
     * The sent rows with `id` (int|null) and `variant_id`: a saved row keeps its own variant,
     * whatever was sent.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsWithVariants(): array
    {
        $rows = array_values((array) $this->input('promotions', []));
        $ids = array_filter(array_map(fn (array $row): ?int => isset($row['id']) ? (int) $row['id'] : null, $rows));
        $saved = Promotion::query()->whereKey($ids)->pluck('product_variant_id', 'id');

        return array_map(function (array $row) use ($saved): array {
            $id = isset($row['id']) ? (int) $row['id'] : null;

            return [...$row, 'id' => $id, 'variant_id' => $id === null ? (int) $row['variant_id'] : (int) $saved[$id]];
        }, $rows);
    }

    /**
     * Indexes of sent rows whose dates overlap another row of the same variant: another sent row,
     * or a saved one that is neither sent nor deleted.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    private function overlapping(array $rows): array
    {
        $sentIds = array_filter(array_column($rows, 'id'));
        $deleted = array_map(intval(...), (array) $this->input('deleted', []));
        $windows = [];

        foreach ($rows as $i => $row) {
            $windows[$row['variant_id']][] = [
                'index' => $i,
                'start' => CarbonImmutable::parse((string) $row['starts_at']),
                'end' => CarbonImmutable::parse((string) $row['ends_at']),
            ];
        }

        $others = Promotion::query()
            ->whereIn('product_variant_id', array_keys($windows))
            ->whereKeyNot([...$sentIds, ...$deleted])
            ->get(['product_variant_id', 'starts_at', 'ends_at']);

        foreach ($others as $other) {
            $windows[$other->product_variant_id][] = ['index' => null, 'start' => $other->starts_at, 'end' => $other->ends_at];
        }

        $overlapping = [];

        foreach ($windows as $variantWindows) {
            foreach ($variantWindows as $a => $first) {
                foreach ($variantWindows as $b => $second) {
                    if ($a !== $b && $first['index'] !== null && $first['start']->lt($second['end']) && $second['start']->lt($first['end'])) {
                        $overlapping[] = $first['index'];

                        break;
                    }
                }
            }
        }

        sort($overlapping);

        return $overlapping;
    }
}
