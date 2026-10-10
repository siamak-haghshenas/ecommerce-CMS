<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Data\Promotions\PromotionData;
use App\Enums\PromotionTargetType;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Support\Pricing\PromotionSet;

final class CountPromotionProductsAction
{
    /**
     * How many products (not in the trash) a promotion would apply to, whatever its dates: the
     * editor's "affects N products" preview.
     */
    public function handle(PromotionData $data): int
    {
        $targets = [];

        foreach ([
            [PromotionTargetType::Product, $data->productIds, false],
            [PromotionTargetType::Category, $data->categoryIds, false],
            [PromotionTargetType::Brand, $data->brandIds, false],
            [PromotionTargetType::Tag, $data->tagIds, false],
            [PromotionTargetType::Product, $data->excludedProductIds, true],
        ] as [$type, $ids, $excluded]) {
            foreach ($ids as $id) {
                $targets[] = new PromotionTarget(['target_type' => $type, 'target_id' => $id, 'is_excluded' => $excluded]);
            }
        }

        // An unsaved promotion with these targets, matched like the real ones.
        $promotion = new Promotion(['scope' => $data->scope]);
        $promotion->id = 0;
        $promotion->setRelation('targets', collect($targets));

        $set = new PromotionSet(collect([$promotion]));
        $count = 0;

        Product::query()->with(['categories:id', 'tags:id'])->chunkById(500, function ($products) use ($set, &$count): void {
            foreach ($products as $product) {
                if ($set->forProduct($product->id, $product->brand_id, $product->categories->modelKeys(), $product->tags->modelKeys()) !== []) {
                    $count++;
                }
            }
        });

        return $count;
    }
}
