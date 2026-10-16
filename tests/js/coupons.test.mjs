// The coupon editor's code rules, checks and payload (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    couponProblems,
    emptyForm,
    generateCode,
    mergeLinks,
    normalizeCode,
    toCouponPayload,
    usagePercent,
} from '../../resources/js/admin/modules/discounts/coupons.ts';

const form = (overrides = {}) => ({
    ...emptyForm('2026-10-16T10:00'),
    code: 'YALDA20',
    name: 'Yalda',
    value: 20,
    ...overrides,
});
const fromLocal = (value) => (value === '' ? null : `${value}Z`);

describe('normalizeCode', () => {
    it('keeps Latin letters and digits in uppercase, converting Persian and Arabic digits', () => {
        assert.equal(normalizeCode('yalda ۱۴٠۵-x'), 'YALDA1405X');
    });

    it('drops other letters and stops at 50 characters', () => {
        assert.equal(normalizeCode('تخفیفABC'), 'ABC');
        assert.equal(normalizeCode('A'.repeat(60)).length, 50);
    });
});

describe('generateCode', () => {
    it('uses only unambiguous letters and digits', () => {
        const code = generateCode(12);
        assert.equal(code.length, 12);
        assert.match(code, /^[A-HJKMNP-Z2-9]+$/);
    });
});

describe('couponProblems', () => {
    it('accepts a complete coupon', () => {
        assert.deepEqual(couponProblems(form()), {});
    });

    it('requires a code of at least 3 characters and a name', () => {
        assert.deepEqual(couponProblems(form({ code: '', name: ' ' })), { code: 'required', name: 'required' });
        assert.equal(couponProblems(form({ code: 'AB' })).code, 'code_length');
    });

    it('keeps a percent between 1 and 90 and a fixed amount above zero', () => {
        assert.equal(couponProblems(form({ value: 91 })).value, 'percent_range');
        assert.equal(couponProblems(form({ value: null })).value, 'required');
        assert.equal(couponProblems(form({ type: 'fixed', value: 0 })).value, 'positive');
        assert.equal(couponProblems(form({ type: 'fixed', value: 500_000 })).value, undefined);
    });

    it('needs no value for free shipping', () => {
        assert.deepEqual(couponProblems(form({ type: 'free_shipping', value: null })), {});
    });

    it('needs something to apply to and someone to use it when those are chosen', () => {
        const problems = couponProblems(form({ appliesTo: 'selected', customerScope: 'selected' }));
        assert.equal(problems.applies_to, 'no_targets');
        assert.equal(problems.customer_ids, 'no_customers');
    });

    it('refuses an end before the start', () => {
        assert.equal(couponProblems(form({ endsAt: '2026-10-15T10:00' })).ends_at, 'end_before_start');
    });
});

describe('toCouponPayload', () => {
    it('sends no hidden values of another type or scope', () => {
        const payload = toCouponPayload(
            form({
                type: 'free_shipping',
                maxDiscount: 1000,
                targets: { categories: [{ id: 3, name: 'Shoes' }], brands: [], tags: [], products: [] },
                customers: [{ id: 9, name: 'Sara', phone_number: '09120000000', used_count: 0 }],
            }),
            fromLocal,
        );
        assert.equal(payload.value, null);
        assert.equal(payload.max_discount, null);
        assert.deepEqual(payload.category_ids, []);
        assert.deepEqual(payload.customer_ids, []);
        assert.equal(payload.starts_at, '2026-10-16T10:00Z');
        assert.equal(payload.ends_at, null);
    });

    it('sends the chosen items and customers by id', () => {
        const payload = toCouponPayload(
            form({
                appliesTo: 'selected',
                targets: {
                    categories: [{ id: 3, name: 'Shoes' }],
                    brands: [{ id: 5, name: 'Nike' }],
                    tags: [],
                    products: [],
                },
                excluded: [{ id: 7, name: 'Socks' }],
                customerScope: 'selected',
                customers: [{ id: 9, name: 'Sara', phone_number: '09120000000', used_count: 0 }],
            }),
            fromLocal,
        );
        assert.deepEqual(payload.category_ids, [3]);
        assert.deepEqual(payload.brand_ids, [5]);
        assert.deepEqual(payload.excluded_product_ids, [7]);
        assert.deepEqual(payload.customer_ids, [9]);
    });
});

describe('helpers', () => {
    it('reports how full the usage limit is', () => {
        assert.equal(usagePercent(42, 100), 42);
        assert.equal(usagePercent(40, 30), 100);
        assert.equal(usagePercent(5, null), null);
    });

    it('adds only links not chosen yet', () => {
        assert.deepEqual(
            mergeLinks([{ id: 1 }, { id: 2 }], [{ id: 2 }, { id: 3 }, { id: 3 }]).map((link) => link.id),
            [1, 2, 3],
        );
    });
});
