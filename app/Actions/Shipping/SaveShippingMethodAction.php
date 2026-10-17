<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Data\Shipping\ShippingMethodData;
use App\Data\Shipping\ShippingRateData;
use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SaveShippingMethodAction
{
    /**
     * Create ($method null; it goes last) or update a shipping method with its ranges (weight,
     * volume) or cities (courier). Fields of the other types are cleared, so changing the type
     * leaves nothing behind.
     */
    public function handle(User $actor, ?ShippingMethod $method, ShippingMethodData $data): ShippingMethod
    {
        return DB::transaction(function () use ($actor, $method, $data): ShippingMethod {
            $creating = $method === null;
            $method ??= new ShippingMethod([
                'created_by' => $actor->id,
                'position' => (int) ShippingMethod::query()->max('position') + 1,
            ]);
            $terms = $data->terms;

            $method->fill([
                'name' => $data->name,
                'description' => $data->description,
                'type' => $terms->type,
                'fee_on_delivery' => $terms->feeOnDelivery,
                'default_fee' => $terms->defaultFee,
                'packaging_weight_grams' => $terms->packagingWeightGrams,
                'min_order_amount' => $terms->minOrderAmount,
                'free_over_amount' => $terms->freeOverAmount,
                'delivery_days_min' => $data->deliveryDaysMin,
                'delivery_days_max' => $data->deliveryDaysMax,
                'is_active' => $terms->isActive,
            ]);

            $changedFields = array_values(array_diff(array_keys($method->getDirty()), ['created_by', 'position']));
            $method->updated_by = $actor->id;
            $method->save();

            $current = $method->rates()->get()->map(fn (ShippingRate $rate): ShippingRateData => ShippingRateData::of($rate))->all();

            if ($current != $terms->rates) {
                $method->rates()->delete();
                $method->rates()->createMany(array_map(fn (ShippingRateData $rate): array => [
                    'range_start' => $rate->start,
                    'range_end' => $rate->end,
                    'fee' => $rate->fee,
                ], $terms->rates));
                $changedFields[] = 'rates';
            }

            $cities = [];

            foreach ($terms->type === ShippingMethodType::Courier ? $terms->cityFees : [] as $cityId => $fee) {
                $cities[$cityId] = ['fee' => $fee];
            }

            $synced = $method->cities()->sync($cities);

            if ($synced['attached'] !== [] || $synced['detached'] !== [] || $synced['updated'] !== []) {
                $changedFields[] = 'cities';
            }

            if (! $creating && $changedFields === []) {
                return $method;
            }

            $event = $creating ? 'shipping_method.created' : 'shipping_method.updated';

            activity('admin')
                ->causedBy($actor)
                ->performedOn($method)
                ->event($event)
                ->withProperties($creating ? ['name' => $method->name] : ['name' => $method->name, 'fields' => $changedFields])
                ->log($event);

            return $method;
        });
    }
}
