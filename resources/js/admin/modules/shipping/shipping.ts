// The shipping editor's form: ranges that always follow on from each other, the checks the server
// repeats on save, the payload, and the list's fee summaries. No Vue, so
// `tests/js/shipping.test.mjs` runs it with Node.
import type { SaveShippingMethodPayload, ShippingCity, ShippingMethod, ShippingRate, ShippingType } from './types';

/** Ranges are typed in kilos (weight) or litres (volume) and stored in grams or cm³. */
export const MEASURE_FACTOR = 1000;

/** Decimal places the range fields accept: whole grams and cm³. */
export const MEASURE_DECIMALS = 3;

/** ShippingMethod::MAX_RATES / MAX_CITIES */
export const MAX_RATES = 50;
export const MAX_CITIES = 500;

/** A range row: its start is always the previous row's end (the first starts at 0). */
export interface RateRow {
    end: number | null;
    fee: number | null;
}

export interface ShippingForm {
    name: string;
    description: string;
    type: ShippingType;
    feeOnDelivery: boolean;
    defaultFee: number | null;
    packagingWeightGrams: number | null;
    minOrderAmount: number | null;
    freeOverAmount: number | null;
    daysMin: number | null;
    daysMax: number | null;
    isActive: boolean;
    rates: RateRow[];
    cities: ShippingCity[];
}

export type ShippingProblem =
    | 'required'
    | 'ratesRequired'
    | 'endRequired'
    | 'endAfterStart'
    | 'citiesRequired'
    | 'tooManyCities'
    | 'tooManyRates'
    | 'daysOrder';

/** A new method starts switched off, so customers don't see it half set up. */
export function emptyForm(type: ShippingType = 'weight'): ShippingForm {
    return {
        name: '',
        description: '',
        type,
        feeOnDelivery: false,
        defaultFee: null,
        packagingWeightGrams: null,
        minOrderAmount: null,
        freeOverAmount: null,
        daysMin: null,
        daysMax: null,
        isActive: false,
        rates: type === 'courier' ? [] : [{ end: null, fee: null }],
        cities: [],
    };
}

export function formFromMethod(method: ShippingMethod): ShippingForm {
    return {
        name: method.name,
        description: method.description ?? '',
        type: method.type,
        feeOnDelivery: method.fee_on_delivery,
        defaultFee: method.default_fee,
        packagingWeightGrams: method.packaging_weight_grams,
        minOrderAmount: method.min_order_amount,
        freeOverAmount: method.free_over_amount,
        daysMin: method.delivery_days_min,
        daysMax: method.delivery_days_max,
        isActive: method.is_active,
        rates: method.rates.map((rate) => ({ end: rate.end, fee: rate.fee })),
        cities: method.cities.map((city) => ({ ...city, province: { ...city.province } })),
    };
}

/** Where each row starts: 0, then the previous row's end (null while that end is empty). */
export function rateStarts(rates: RateRow[]): (number | null)[] {
    return rates.map((_, index) => (index === 0 ? 0 : (rates[index - 1]?.end ?? null)));
}

/** A new last row, starting where the current last one ends. */
export function addRate(rates: RateRow[]): RateRow[] {
    return [...rates, { end: null, fee: null }];
}

/** Removing a row makes the next one start where the previous one ends, so no gap appears. */
export function removeRate(rates: RateRow[], index: number): RateRow[] {
    return rates.filter((_, other) => other !== index);
}

/**
 * Switching the type clears what belonged to the old one (the server does too): ranges for
 * couriers, cities and the default fee for weight and volume, packaging for anything but weight.
 */
export function changeType(form: ShippingForm, type: ShippingType): ShippingForm {
    if (form.type === type) {
        return form;
    }

    return {
        ...form,
        type,
        rates: type === 'courier' ? [] : form.type === 'courier' ? [{ end: null, fee: null }] : form.rates,
        cities: type === 'courier' ? form.cities : [],
        defaultFee: type === 'courier' ? form.defaultFee : null,
        packagingWeightGrams: type === 'weight' ? form.packagingWeightGrams : null,
    };
}

export function toShippingPayload(form: ShippingForm): SaveShippingMethodPayload {
    const starts = rateStarts(form.rates);
    const courier = form.type === 'courier';

    return {
        name: form.name.trim(),
        description: form.description.trim() || null,
        type: form.type,
        fee_on_delivery: form.feeOnDelivery,
        default_fee: courier ? form.defaultFee : null,
        packaging_weight_grams: form.type === 'weight' ? form.packagingWeightGrams : null,
        min_order_amount: form.minOrderAmount,
        free_over_amount: form.freeOverAmount,
        delivery_days_min: form.daysMin,
        delivery_days_max: form.daysMax,
        is_active: form.isActive,
        rates: courier
            ? null
            : form.rates.map((rate, index): ShippingRate => ({
                  start: starts[index] ?? 0,
                  end: rate.end,
                  fee: rate.fee ?? 0,
              })),
        cities: courier ? form.cities.map((city) => ({ id: city.id, fee: city.fee })) : null,
    };
}

/**
 * The checks the server repeats, keyed like its errors (`rates.2.end`, `default_fee`, ...).
 * Each row's start follows from the previous end, so gaps and overlaps can't happen; an end
 * must be above its start and only the last row may be left open.
 */
export function shippingProblems(form: ShippingForm): Partial<Record<string, ShippingProblem>> {
    const problems: Partial<Record<string, ShippingProblem>> = {};

    if (form.name.trim() === '') {
        problems.name = 'required';
    }

    if (form.daysMin !== null && form.daysMax !== null && form.daysMax < form.daysMin) {
        problems.delivery_days_max = 'daysOrder';
    }

    if (form.type === 'courier') {
        if (form.defaultFee === null) {
            problems.default_fee = 'required';
        }

        if (form.cities.length === 0) {
            problems.cities = 'citiesRequired';
        } else if (form.cities.length > MAX_CITIES) {
            problems.cities = 'tooManyCities';
        }

        return problems;
    }

    if (form.rates.length === 0) {
        problems.rates = 'ratesRequired';
    } else if (form.rates.length > MAX_RATES) {
        problems.rates = 'tooManyRates';
    }

    const starts = rateStarts(form.rates);
    const last = form.rates.length - 1;

    form.rates.forEach((rate, index) => {
        const start = starts[index];

        if (rate.end === null) {
            if (index !== last) {
                problems[`rates.${index}.end`] = 'endRequired';
            }
        } else if (start !== null && start !== undefined && rate.end <= start) {
            problems[`rates.${index}.end`] = 'endAfterStart';
        }

        if (rate.fee === null) {
            problems[`rates.${index}.fee`] = 'required';
        }
    });

    return problems;
}

/** Adds cities that aren't in the list yet, with the default fee; keeps the order and fees of the others. */
export function mergeCities(current: ShippingCity[], picked: ShippingCity[]): ShippingCity[] {
    const known = new Set(current.map((city) => city.id));

    return [...current, ...picked.filter((city) => !known.has(city.id)).map((city) => ({ ...city, fee: null }))];
}

/** The lowest and highest fee a method can charge (before free shipping), or null when it has none. */
export function feeRange(method: Pick<ShippingMethod, 'type' | 'rates' | 'cities' | 'default_fee'>): {
    min: number;
    max: number;
} | null {
    const fees =
        method.type === 'courier'
            ? method.cities.map((city) => city.fee ?? method.default_fee).filter((fee): fee is number => fee !== null)
            : method.rates.map((rate) => rate.fee);

    return fees.length === 0 ? null : { min: Math.min(...fees), max: Math.max(...fees) };
}

/** The upper limit of a weight or volume method (grams or cm³), or null when its last range is open. */
export function measureLimit(method: Pick<ShippingMethod, 'type' | 'rates'>): number | null {
    if (method.type === 'courier' || method.rates.length === 0) {
        return null;
    }

    return method.rates[method.rates.length - 1]?.end ?? null;
}
