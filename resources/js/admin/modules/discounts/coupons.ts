// The coupon editor's form: the code rules, a generated code, the checks the server repeats on
// save and the payload. No Vue, so `tests/js/coupons.test.mjs` runs it with Node.
import type {
    CouponCustomer,
    CouponDetail,
    CouponLink,
    CouponTargetKind,
    CouponTargets,
    CouponType,
    SaveCouponPayload,
} from './types';

/** Coupon::MAX_PERCENT, like promotions and sale events. */
export const MAX_PERCENT = 90;

/** Coupon::CODE_MIN / CODE_MAX */
export const CODE_MIN = 3;
export const CODE_MAX = 50;

export const TARGET_KINDS: CouponTargetKind[] = ['categories', 'brands', 'tags', 'products'];

/** The editor's fields; dates are `datetime-local` values. */
export interface CouponForm {
    code: string;
    name: string;
    type: CouponType;
    value: number | null;
    maxDiscount: number | null;
    minOrderAmount: number | null;
    appliesTo: 'all' | 'selected';
    targets: CouponTargets;
    excluded: CouponLink[];
    excludeDiscounted: boolean;
    customerScope: 'all' | 'selected';
    customers: CouponCustomer[];
    usageLimit: number | null;
    perCustomerLimit: number | null;
    firstOrderOnly: boolean;
    startsAt: string;
    endsAt: string;
    isActive: boolean;
}

export const emptyTargets = (): CouponTargets => ({ categories: [], brands: [], tags: [], products: [] });

const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

/** What the code field keeps of the typed text: Latin letters and digits (any script), uppercase. */
export function normalizeCode(input: string): string {
    return input
        .replace(/[۰-۹٠-٩]/g, (digit) =>
            String(PERSIAN_DIGITS.includes(digit) ? PERSIAN_DIGITS.indexOf(digit) : ARABIC_DIGITS.indexOf(digit)),
        )
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, CODE_MAX);
}

/** Letters and digits that can't be mistaken for one another (no 0/O, 1/I/L). */
const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

/** A random code to hand out, e.g. "K7QM2XPA". */
export function generateCode(length = 8, random: () => number = Math.random): string {
    return Array.from({ length }, () => CODE_ALPHABET[Math.floor(random() * CODE_ALPHABET.length)]).join('');
}

/** A new coupon's fields: percent, every product and customer, once per customer, starting `now`. */
export function emptyForm(now: string): CouponForm {
    return {
        code: '',
        name: '',
        type: 'percent',
        value: null,
        maxDiscount: null,
        minOrderAmount: null,
        appliesTo: 'all',
        targets: emptyTargets(),
        excluded: [],
        excludeDiscounted: false,
        customerScope: 'all',
        customers: [],
        usageLimit: null,
        perCustomerLimit: 1,
        firstOrderOnly: false,
        startsAt: now,
        endsAt: '',
        isActive: true,
    };
}

export function formFromCoupon(coupon: CouponDetail, toLocal: (iso: string | null) => string): CouponForm {
    return {
        code: coupon.code,
        name: coupon.name,
        type: coupon.type,
        value: coupon.value,
        maxDiscount: coupon.max_discount,
        minOrderAmount: coupon.min_order_amount,
        appliesTo: coupon.applies_to,
        targets: {
            categories: [...coupon.targets.categories],
            brands: [...coupon.targets.brands],
            tags: [...coupon.targets.tags],
            products: [...coupon.targets.products],
        },
        excluded: [...coupon.excluded_products],
        excludeDiscounted: coupon.exclude_discounted,
        customerScope: coupon.customer_scope,
        customers: [...coupon.customers],
        usageLimit: coupon.usage_limit,
        perCustomerLimit: coupon.per_customer_limit,
        firstOrderOnly: coupon.first_order_only,
        startsAt: toLocal(coupon.starts_at),
        endsAt: toLocal(coupon.ends_at),
        isActive: coupon.is_active,
    };
}

const ids = (links: { id: number }[]): number[] => links.map((link) => link.id);

/**
 * What is saved. Fields the chosen type or scope doesn't use are sent empty, so switching back
 * and forth in the form never saves a hidden value.
 */
export function toCouponPayload(form: CouponForm, fromLocal: (value: string) => string | null): SaveCouponPayload {
    const selected = form.appliesTo === 'selected';

    return {
        code: form.code,
        name: form.name.trim(),
        type: form.type,
        value: form.type === 'free_shipping' ? null : form.value,
        max_discount: form.type === 'percent' ? form.maxDiscount : null,
        min_order_amount: form.minOrderAmount,
        applies_to: form.appliesTo,
        category_ids: selected ? ids(form.targets.categories) : [],
        brand_ids: selected ? ids(form.targets.brands) : [],
        tag_ids: selected ? ids(form.targets.tags) : [],
        product_ids: selected ? ids(form.targets.products) : [],
        excluded_product_ids: ids(form.excluded),
        exclude_discounted: form.excludeDiscounted,
        customer_scope: form.customerScope,
        customer_ids: form.customerScope === 'selected' ? ids(form.customers) : [],
        usage_limit: form.usageLimit,
        per_customer_limit: form.perCustomerLimit,
        first_order_only: form.firstOrderOnly,
        starts_at: fromLocal(form.startsAt),
        ends_at: fromLocal(form.endsAt),
        is_active: form.isActive,
    };
}

export type CouponProblem =
    'required' | 'code_length' | 'percent_range' | 'positive' | 'no_targets' | 'no_customers' | 'end_before_start';

/** The checks the server repeats on save, by payload field; empty when the form can be saved. */
export function couponProblems(form: CouponForm): Partial<Record<keyof SaveCouponPayload, CouponProblem>> {
    const problems: Partial<Record<keyof SaveCouponPayload, CouponProblem>> = {};

    if (form.code === '') {
        problems.code = 'required';
    } else if (form.code.length < CODE_MIN) {
        problems.code = 'code_length';
    }

    if (form.name.trim() === '') {
        problems.name = 'required';
    }

    if (form.type !== 'free_shipping') {
        if (form.value === null) {
            problems.value = 'required';
        } else if (form.type === 'percent' && (form.value < 1 || form.value > MAX_PERCENT)) {
            problems.value = 'percent_range';
        } else if (form.value < 1) {
            problems.value = 'positive';
        }
    }

    if (form.type === 'percent' && form.maxDiscount !== null && form.maxDiscount < 1) {
        problems.max_discount = 'positive';
    }

    for (const field of ['usageLimit', 'perCustomerLimit'] as const) {
        if (form[field] !== null && (form[field] as number) < 1) {
            problems[field === 'usageLimit' ? 'usage_limit' : 'per_customer_limit'] = 'positive';
        }
    }

    if (form.appliesTo === 'selected' && TARGET_KINDS.every((kind) => form.targets[kind].length === 0)) {
        problems.applies_to = 'no_targets';
    }

    if (form.customerScope === 'selected' && form.customers.length === 0) {
        problems.customer_ids = 'no_customers';
    }

    if (form.startsAt !== '' && form.endsAt !== '' && form.endsAt <= form.startsAt) {
        problems.ends_at = 'end_before_start';
    }

    return problems;
}

/** How full the usage limit is, 0–100; null when unlimited. */
export function usagePercent(used: number, limit: number | null): number | null {
    return limit === null || limit <= 0 ? null : Math.min(100, Math.round((used / limit) * 100));
}

/** Adds links not chosen yet, keeping the order: the chosen ones first, then the new ones. */
export function mergeLinks<T extends { id: number }>(current: T[], added: T[]): T[] {
    const known = new Set(current.map((link) => link.id));
    const merged = [...current];

    for (const link of added) {
        if (!known.has(link.id)) {
            known.add(link.id);
            merged.push(link);
        }
    }

    return merged;
}
