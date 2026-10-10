<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Actions\Pricing\RefreshPricesAction;
use App\Data\Promotions\PromotionData;
use App\Enums\PromotionTargetType;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SavePromotionAction
{
    public function __construct(private RefreshPricesAction $refreshPrices) {}

    /**
     * Create ($promotion null) or update a promotion and its targets, then reprice the catalog.
     */
    public function handle(User $actor, ?Promotion $promotion, PromotionData $data): Promotion
    {
        $promotion = DB::transaction(function () use ($actor, $promotion, $data): Promotion {
            $creating = $promotion === null;
            $promotion ??= new Promotion(['created_by' => $actor->id]);

            $promotion->fill([
                'name' => $data->name,
                'type' => $data->type,
                'value' => $data->value,
                'max_discount' => $data->maxDiscount,
                'scope' => $data->scope,
                'starts_at' => $data->startsAt ?? $promotion->starts_at ?? now(),
                'ends_at' => $data->endsAt,
                'is_active' => $data->isActive,
            ]);

            $changedFields = array_keys($promotion->getDirty());
            $promotion->updated_by = $actor->id;
            $promotion->save();

            if ($this->syncTargets($promotion, $data)) {
                $changedFields[] = 'targets';
            }

            if (! $creating && $changedFields === []) {
                return $promotion;
            }

            $event = $creating ? 'promotion.created' : 'promotion.updated';

            activity('admin')
                ->causedBy($actor)
                ->performedOn($promotion)
                ->event($event)
                ->withProperties($creating ? ['name' => $promotion->name] : ['name' => $promotion->name, 'fields' => $changedFields])
                ->log($event);

            return $promotion;
        });

        // Any product may gain or lose this promotion (targets, dates, amount).
        $this->refreshPrices->handle();

        return $promotion;
    }

    /**
     * Replace the targets; reports whether they changed.
     */
    private function syncTargets(Promotion $promotion, PromotionData $data): bool
    {
        $wanted = [];

        foreach ([
            [PromotionTargetType::Product, $data->productIds, false],
            [PromotionTargetType::Category, $data->categoryIds, false],
            [PromotionTargetType::Brand, $data->brandIds, false],
            [PromotionTargetType::Tag, $data->tagIds, false],
            [PromotionTargetType::Product, $data->excludedProductIds, true],
        ] as [$type, $ids, $excluded]) {
            foreach ($ids as $id) {
                $wanted[$this->key($type, $id, $excluded)] = ['target_type' => $type, 'target_id' => $id, 'is_excluded' => $excluded];
            }
        }

        $current = $promotion->targets()->get()
            ->mapWithKeys(fn (PromotionTarget $target): array => [$this->key($target->target_type, $target->target_id, $target->is_excluded) => true])
            ->all();

        ksort($wanted);
        ksort($current);

        if (array_keys($wanted) === array_keys($current)) {
            return false;
        }

        $promotion->targets()->delete();
        $promotion->targets()->createMany(array_values($wanted));

        return true;
    }

    private function key(PromotionTargetType $type, int $id, bool $excluded): string
    {
        return $type->value.':'.$id.':'.($excluded ? '1' : '0');
    }
}
