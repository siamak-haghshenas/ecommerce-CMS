/** IRT = Toman, IRR = Rial. Amounts are always stored in Rials (1 Toman = 10 Rials). */
export type PriceDisplayCurrency = 'IRT' | 'IRR';

export interface StoreSettings {
    price_display_currency: PriceDisplayCurrency;
    low_stock_threshold: number;
    /** Where a storefront shows previews, with a {token} placeholder; null when not set. */
    storefront_preview_url: string | null;
    /** Grams shipping uses for a product without a weight; null: such products can't use weight shipping. */
    default_weight_grams: number | null;
}
