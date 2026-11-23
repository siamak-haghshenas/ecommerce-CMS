import { ref } from 'vue';
import type { Currency } from './types';

/** The store's display currency, taken from the latest `meta.currency` the API sent (Toman by default). */
export const currency = ref<Currency>({ display: 'IRT', rials_per_unit: 10 });

export function rememberCurrency(next: Currency): void {
    if (next.display !== currency.value.display || next.rials_per_unit !== currency.value.rials_per_unit) {
        currency.value = next;
    }
}

const numberFormat = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 });

/** A number in Persian digits with the Persian thousands separator (۳٬۲۳۰٬۰۰۰). */
export function formatNumber(value: number): string {
    return numberFormat.format(value);
}

/** An amount in Rials, shown in the display currency without its unit. */
export function formatAmount(rials: number): string {
    return formatNumber(Math.floor(rials / currency.value.rials_per_unit));
}

export function currencyLabel(): string {
    return currency.value.display === 'IRT' ? 'تومان' : 'ریال';
}
