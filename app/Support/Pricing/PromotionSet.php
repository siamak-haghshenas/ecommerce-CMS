<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Enums\CategoryType;
use App\Enums\PromotionScope;
use App\Enums\PromotionTargetType;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Support\CategoryTree;
use Illuminate\Support\Collection;

/**
 * The promotions prices depend on (enabled, not ended), with their targets resolved once:
 * categories are expanded to their sub-categories.
 */
final class PromotionSet
{
    /** @var array<int, array{products: array<int, true>, categories: array<int, true>, brands: array<int, true>, tags: array<int, true>, excluded: array<int, true>}> */
    private array $targets = [];

    /**
     * @param  Collection<int, Promotion>  $promotions  with their targets loaded
     */
    public function __construct(private readonly Collection $promotions)
    {
        $tree = CategoryTree::current();

        foreach ($promotions as $promotion) {
            $sets = ['products' => [], 'categories' => [], 'brands' => [], 'tags' => [], 'excluded' => []];

            /** @var PromotionTarget $target */
            foreach ($promotion->targets as $target) {
                if ($target->is_excluded) {
                    $sets['excluded'][$target->target_id] = true;

                    continue;
                }

                match ($target->target_type) {
                    PromotionTargetType::Product => $sets['products'][$target->target_id] = true,
                    PromotionTargetType::Brand => $sets['brands'][$target->target_id] = true,
                    PromotionTargetType::Tag => $sets['tags'][$target->target_id] = true,
                    PromotionTargetType::Category => $sets['categories'] += array_fill_keys(
                        [$target->target_id, ...($tree->find($target->target_id)?->type === CategoryType::Product ? $tree->descendantIds($target->target_id) : [])],
                        true,
                    ),
                };
            }

            $this->targets[$promotion->id] = $sets;
        }
    }

    public static function load(): self
    {
        return new self(Promotion::query()->relevant()->with('targets')->get());
    }

    public function isEmpty(): bool
    {
        return $this->promotions->isEmpty();
    }

    /**
     * The promotions that target a product, whatever their time window.
     *
     * @param  list<int>  $categoryIds
     * @param  list<int>  $tagIds
     * @return list<Promotion>
     */
    public function forProduct(int $productId, ?int $brandId, array $categoryIds, array $tagIds): array
    {
        $matching = [];

        foreach ($this->promotions as $promotion) {
            $sets = $this->targets[$promotion->id];

            if (isset($sets['excluded'][$productId])) {
                continue;
            }

            $targeted = $promotion->scope === PromotionScope::All
                || isset($sets['products'][$productId])
                || ($brandId !== null && isset($sets['brands'][$brandId]))
                || array_intersect_key($sets['categories'], array_flip($categoryIds)) !== []
                || array_intersect_key($sets['tags'], array_flip($tagIds)) !== [];

            if ($targeted) {
                $matching[] = $promotion;
            }
        }

        return $matching;
    }
}
