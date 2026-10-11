<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Actions\Pricing\RefreshPricesAction;
use App\Actions\Seo\ScoreSeoAction;
use App\Data\SaleEvents\SaleEventData;
use App\Data\SaleEvents\SaleEventItemData;
use App\Enums\MediaUsageRole;
use App\Models\ProductVariant;
use App\Models\SaleEvent;
use App\Models\SaleEventItem;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;

final readonly class SaveSaleEventAction
{
    public function __construct(
        private RefreshPricesAction $refreshPrices,
        private ScoreSeoAction $scoreSeo,
    ) {}

    /**
     * Create ($saleEvent null; it goes last in priority) or update an event with all its variants
     * and its landing page, then reprice the products it had or has.
     */
    public function handle(User $actor, ?SaleEvent $saleEvent, SaleEventData $data): SaleEvent
    {
        $before = $saleEvent === null ? [] : $saleEvent->items()->distinct()->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->all();

        $saleEvent = DB::transaction(function () use ($actor, $saleEvent, $data): SaleEvent {
            $creating = $saleEvent === null;
            $saleEvent ??= new SaleEvent([
                'created_by' => $actor->id,
                'position' => (SaleEvent::query()->max('position') ?? -1) + 1,
            ]);
            $previousKeyphrase = $saleEvent->focus_keyphrase;

            $saleEvent->fill([
                'name' => $data->name,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'is_active' => $data->isActive,
                'has_landing_page' => $data->hasLandingPage,
                'slug' => $data->slug,
                'landing_title' => $data->landingTitle,
                'landing_description' => $data->landingDescription,
                'meta_title' => $data->metaTitle,
                'meta_description' => $data->metaDescription,
                'focus_keyphrase' => $data->focusKeyphrase,
                'robots_noindex' => $data->robotsNoindex,
                'canonical_url' => $data->canonicalUrl,
                'og_title' => $data->ogTitle,
                'og_description' => $data->ogDescription,
            ]);

            $changedFields = array_values(array_diff(array_keys($saleEvent->getDirty()), ['created_by', 'position']));
            $saleEvent->updated_by = $actor->id;
            $saleEvent->save();

            if ($this->syncItems($saleEvent, $data->items)) {
                $changedFields[] = 'items';
            }

            if ($saleEvent->syncMediaAssets(MediaUsageRole::Banner, $data->bannerId === null ? [] : [$data->bannerId])) {
                $changedFields[] = 'banner';
            }

            if ($saleEvent->syncMediaAssets(MediaUsageRole::OgImage, $data->ogImageId === null ? [] : [$data->ogImageId])) {
                $changedFields[] = 'og_image';
            }

            // Library files shown in the description count as used, so they can't be deleted meanwhile.
            $saleEvent->syncMediaAssets(MediaUsageRole::Content, RichText::mediaIds($saleEvent->landing_description));

            $this->scoreSeo->handle($saleEvent, $previousKeyphrase);

            if (! $creating && $changedFields === []) {
                return $saleEvent;
            }

            $event = $creating ? 'sale_event.created' : 'sale_event.updated';

            activity('admin')
                ->causedBy($actor)
                ->performedOn($saleEvent)
                ->event($event)
                ->withProperties($creating ? ['name' => $saleEvent->name] : ['name' => $saleEvent->name, 'fields' => $changedFields])
                ->log($event);

            return $saleEvent;
        });

        $after = $saleEvent->items()->distinct()->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->refreshPrices->handle(array_values(array_unique([...$before, ...$after])));

        return $saleEvent;
    }

    /**
     * Makes the event hold exactly these variants: rows are updated by variant, new ones created
     * and missing ones removed. Reports whether anything changed.
     *
     * @param  list<SaleEventItemData>  $items
     */
    private function syncItems(SaleEvent $saleEvent, array $items): bool
    {
        $current = $saleEvent->items()->get()->keyBy('product_variant_id');
        $productIds = ProductVariant::withTrashed()
            ->whereKey(array_map(fn (SaleEventItemData $item): int => $item->variantId, $items))
            ->pluck('product_id', 'id');
        $changed = false;

        foreach ($items as $data) {
            /** @var SaleEventItem $item */
            $item = $current->pull($data->variantId) ?? new SaleEventItem(['sale_event_id' => $saleEvent->id, 'product_variant_id' => $data->variantId]);

            $item->fill([
                'product_id' => (int) $productIds[$data->variantId],
                'discount_type' => $data->discountType,
                'discount_value' => $data->discountValue,
                'max_per_order' => $data->maxPerOrder,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
            ]);

            if (! $item->exists || $item->isDirty()) {
                $item->save();
                $changed = true;
            }
        }

        if ($current->isNotEmpty()) {
            SaleEventItem::query()->whereKey($current->modelKeys())->delete();
            $changed = true;
        }

        return $changed;
    }
}
