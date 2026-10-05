<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PriceDisplayCurrency;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Store settings with their types and defaults. Values live in the `settings` table and are
 * cached as one array; saving clears the cache. Add a setting: a constant, a default and a getter.
 */
final class Settings
{
    public const string PRICE_DISPLAY_CURRENCY = 'price_display_currency';

    public const string LOW_STOCK_THRESHOLD = 'low_stock_threshold';

    /** Where a storefront shows previews, with a {token} placeholder; null: not set. */
    public const string STOREFRONT_PREVIEW_URL = 'storefront_preview_url';

    private const array DEFAULTS = [
        self::PRICE_DISPLAY_CURRENCY => 'IRT',
        self::LOW_STOCK_THRESHOLD => 5,
        self::STOREFRONT_PREVIEW_URL => null,
    ];

    private const string CACHE_KEY = 'store-settings';

    public function priceDisplayCurrency(): PriceDisplayCurrency
    {
        return PriceDisplayCurrency::tryFrom((string) $this->get(self::PRICE_DISPLAY_CURRENCY)) ?? PriceDisplayCurrency::Toman;
    }

    public function lowStockThreshold(): int
    {
        return (int) $this->get(self::LOW_STOCK_THRESHOLD);
    }

    public function storefrontPreviewUrl(): ?string
    {
        $url = $this->get(self::STOREFRONT_PREVIEW_URL);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());

        return [...self::DEFAULTS, ...array_intersect_key($stored, self::DEFAULTS)];
    }

    /**
     * @param  array<string, mixed>  $values  only known keys are stored; null goes back to the default
     */
    public function set(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            if ($value === null) {
                Setting::query()->whereKey($key)->delete();
            } else {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        Cache::forget(self::CACHE_KEY);
    }

    private function get(string $key): mixed
    {
        return $this->all()[$key];
    }
}
