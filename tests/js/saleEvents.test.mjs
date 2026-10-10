// The sale event editor's linked fields, rounding, checks, bulk actions and error mapping (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    applyDiscount,
    discountOf,
    groupByProduct,
    linkedValues,
    mapItemErrors,
    rowProblem,
    rowsFromVariants,
    toItemPayload,
    typed,
} from '../../resources/js/admin/modules/discounts/saleEvents.ts';

const row = (overrides = {}) => ({
    variantId: 1,
    productId: 10,
    productName: 'Shoe',
    productType: 'variable',
    productImage: null,
    label: 'M',
    sku: 'SH-M',
    price: 4_500_000,
    currentPrice: 4_500_000,
    discountSource: null,
    saleEventId: null,
    isEnabled: true,
    isDeleted: false,
    field: 'percent',
    value: 20,
    startsAt: '',
    endsAt: '',
    overriddenBy: [],
    ...overrides,
});
const toIso = (value) => (value === '' ? null : `${value}:00Z`);
const event = { startsAt: '2026-12-18T00:00', endsAt: '2026-12-22T23:59' };

describe('linked fields', () => {
    it('fills the amount off and final price from a percentage', () => {
        assert.deepEqual(linkedValues(row(), 10), { percent: 20, off: 900_000, final: 3_600_000 });
    });

    it('fills the percentage and final price from an amount off', () => {
        assert.deepEqual(linkedValues(row({ field: 'off', value: 1_000_000 }), 10), {
            percent: 22.2,
            off: 1_000_000,
            final: 3_500_000,
        });
    });

    it('fills the amount off from a final price', () => {
        assert.deepEqual(linkedValues(row({ field: 'final', value: 4_000_000 }), 10), {
            percent: 11.1,
            off: 500_000,
            final: 4_000_000,
        });
    });

    it('rounds a percentage down to whole Toman like the server', () => {
        assert.equal(discountOf(row({ price: 12_345, value: 10 }), 10), 1_230);
        assert.equal(discountOf(row({ price: 12_345, value: 10 }), 1), 1_234);
    });

    it('leaves every field empty while nothing is typed', () => {
        assert.deepEqual(linkedValues(row({ value: null }), 10), { percent: null, off: null, final: null });
    });
});

describe('payload', () => {
    it('saves a percentage when the % was typed', () => {
        assert.deepEqual(toItemPayload(row(), toIso), {
            variant_id: 1,
            discount_type: 'percent',
            discount_value: 20,
            starts_at: null,
            ends_at: null,
        });
    });

    it('saves a fixed amount off for an amount or a final price', () => {
        assert.deepEqual(toItemPayload(typed(row(), 'off', 700_000), toIso).discount_value, 700_000);
        const fromFinal = toItemPayload(typed(row(), 'final', 4_000_000), toIso);
        assert.equal(fromFinal.discount_type, 'fixed');
        assert.equal(fromFinal.discount_value, 500_000);
    });

    it('sends the variant’s own dates', () => {
        const payload = toItemPayload(row({ startsAt: '2026-12-20T00:00', endsAt: '2026-12-21T00:00' }), toIso);
        assert.equal(payload.starts_at, '2026-12-20T00:00:00Z');
        assert.equal(payload.ends_at, '2026-12-21T00:00:00Z');
    });
});

describe('checks', () => {
    it('accepts a valid row', () => {
        assert.equal(rowProblem(row(), event), null);
    });

    it('reports a missing discount, a percentage out of range and an amount not below the price', () => {
        assert.equal(rowProblem(row({ value: null }), event), 'required');
        assert.equal(rowProblem(row({ value: 95 }), event), 'percent_range');
        assert.equal(rowProblem(row({ value: 0 }), event), 'percent_range');
        assert.equal(rowProblem(row({ field: 'off', value: 4_500_000 }), event), 'not_below_price');
        assert.equal(rowProblem(row({ field: 'final', value: 0 }), event), 'not_below_price');
        assert.equal(rowProblem(row({ field: 'final', value: 4_600_000 }), event), 'required');
    });

    it('reports own dates in the wrong order or outside the event', () => {
        assert.equal(
            rowProblem(row({ startsAt: '2026-12-21T00:00', endsAt: '2026-12-20T00:00' }), event),
            'dates_order',
        );
        assert.equal(rowProblem(row({ startsAt: '2026-12-17T00:00' }), event), 'outside_event');
        assert.equal(rowProblem(row({ endsAt: '2026-12-23T00:00' }), event), 'outside_event');
        assert.equal(rowProblem(row({ startsAt: '2026-12-19T00:00', endsAt: '2026-12-22T23:59' }), event), null);
    });
});

describe('bulk actions', () => {
    const rows = [row(), row({ variantId: 2, price: 300_000 }), row({ variantId: 3 })];

    it('sets the same percentage on the chosen rows', () => {
        const { rows: next, skipped } = applyDiscount(rows, new Set([1, 2]), 'percent', 30);
        assert.deepEqual(
            next.map((item) => item.value),
            [30, 30, 20],
        );
        assert.deepEqual(skipped, []);
    });

    it('skips rows an amount off can’t apply to', () => {
        const { rows: next, skipped } = applyDiscount(rows, new Set([1, 2]), 'off', 500_000);
        assert.deepEqual(skipped, [2]);
        assert.equal(next[0].field, 'off');
        assert.equal(next[1].field, 'percent');
    });
});

describe('rows', () => {
    it('adds the variants not yet in the event with the starting discount', () => {
        const variant = (id, discountSource = null) => ({
            variant: {
                id,
                sku: `SKU-${id}`,
                label: 'M · Red',
                price: 100_000,
                sale_price: null,
                current_price: 100_000,
                discount_source: discountSource,
                sale_event_id: null,
                is_enabled: true,
                is_deleted: false,
            },
            product: { id: 20, name: 'Scarf', type: 'variable', image: null, is_deleted: false },
        });
        const added = rowsFromVariants([variant(5, 'sale'), variant(7)], new Set([7]), { field: 'off', value: 20_000 });

        assert.deepEqual(
            added.map((item) => [
                item.variantId,
                item.productName,
                item.label,
                item.field,
                item.value,
                item.discountSource,
            ]),
            [[5, 'Scarf', 'M · Red', 'off', 20_000, null]],
        );
    });

    it('groups rows by product in the order they were added', () => {
        const groups = groupByProduct([row(), row({ variantId: 2, productId: 11 }), row({ variantId: 3 })]);
        assert.deepEqual(
            groups.map((group) => [group.productId, group.rows.map((item) => item.variantId)]),
            [
                [10, [1, 3]],
                [11, [2]],
            ],
        );
    });

    it('maps item errors to their variants by position', () => {
        const errors = mapItemErrors([row(), row({ variantId: 9 })], {
            'items.1.discount_value': ['Too much'],
            name: ['Required'],
        });
        assert.deepEqual([...errors], [[9, 'Too much']]);
    });
});
