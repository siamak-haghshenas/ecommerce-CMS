/** IRT = Toman, IRR = Rial. Amounts are always stored in Rials (1 Toman = 10 Rials). */
export type PriceDisplayCurrency = 'IRT' | 'IRR';

export interface StoreSettings {
    price_display_currency: PriceDisplayCurrency;
    low_stock_threshold: number;
}
