// The promotions page's rows: linked fields, states, checks, changes, bulk edits and error mapping (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    applyBulk,
    isChanged,
    mapErrors,
    newRows,
    rowFromPromotion,
    rowProblem,
    rowState,
    rowValues,
    toPayload,
} from '../../resources/js/admin/modules/discounts/promotions.ts';
import { typed } from '../../resources/js/admin/modules/discounts/saleEvents.ts';

const toLocal = (iso) => (iso ? iso.slice(0, 16) : '');
const toIso = (value) => (value === '' ? null : `${value}:00Z`);

const saved = (overrides = {}) =>
    rowFromPromotion(
        {
            id: 7,
            discount_type: 'percent',
            discount_value: 20,
            max_per_order: 2,
            quantity_limit: 50,
            sold_count: 12,
            starts_at: '2026-10-20T00:00:00Z',
            ends_at: '2026-10-30T23:59:00Z',
            state: 'active',
            variant: {
                id: 3,
                sku: 'TS-S',
                label: 'S',
                price: 220_000,
                sale_price: null,
                current_price: 176_000,
                discount_source: 'promotion',
                sale_event_id: null,
                promotion_id: 7,
                is_enabled: true,
                is_deleted: false,
            },
            product: { id: 1, name: 'T-shirt', type: 'variable', image: null, is_deleted: false },
            ...overrides,
        },
        toLocal,
    );

describe('rows', () => {
    it('links the percentage and the off price, rounded like the server', () => {
        assert.deepEqual(rowValues(saved(), 10), { percent: 20, final: 176_000 });
        assert.deepEqual(rowValues(typed(saved(), 'final', 180_000), 10), { percent: 18.2, final: 180_000 });
    });

    it('saves a percentage when the % was typed, else the amount off', () => {
        assert.deepEqual(toPayload(saved(), toIso), {
            id: 7,
            variant_id: 3,
            discount_type: 'percent',
            discount_value: 20,
            max_per_order: 2,
            quantity_limit: 50,
            starts_at: '2026-10-20T00:00:00Z',
            ends_at: '2026-10-30T23:59:00Z',
        });
        assert.equal(toPayload(typed(saved(), 'final', 180_000), toIso).discount_value, 40_000);
    });

    it('knows what changed: typing the same discount another way is no change', () => {
        assert.equal(isChanged(saved()), false);
        assert.equal(isChanged(typed(saved(), 'final', 176_000)), true);
        assert.equal(isChanged({ ...saved(), maxPerOrder: 3 }), true);
        assert.equal(isChanged(newRows([], {}, 0)[0] ?? { original: null }), true);
    });

    it('gives the state at a moment, sold out included', () => {
        assert.equal(rowState(saved(), '2026-10-19T12:00'), 'scheduled');
        assert.equal(rowState(saved(), '2026-10-25T12:00'), 'active');
        assert.equal(rowState(saved(), '2026-11-01T00:00'), 'ended');
        assert.equal(rowState({ ...saved(), soldCount: 50 }, '2026-10-25T12:00'), 'sold_out');
    });

    it('adds chosen variants as new rows with the starting values', () => {
        const product = { id: 2, name: 'Jeans', type: 'variable', status: 'published', image: null, variants: [] };
        const variant = {
            id: 9,
            sku: 'JN-32',
            label: '32',
            price: 1_000_000,
            current_price: 1_000_000,
            in_stock: true,
            stock_quantity: 6,
            has_promotion: false,
        };
        const [row] = newRows(
            [{ product, variant }],
            {
                percent: 15,
                maxPerOrder: 1,
                quantityLimit: null,
                startsAt: '2026-10-20T00:00',
                endsAt: '2026-10-27T00:00',
            },
            4,
        );

        assert.equal(row.key, 'n4');
        assert.equal(row.id, null);
        assert.deepEqual(rowValues(row, 1), { percent: 15, final: 850_000 });
        assert.equal(row.maxPerOrder, 1);
    });
});

describe('checks', () => {
    it('reports what the server would refuse', () => {
        const rows = [saved()];
        assert.equal(rowProblem(saved(), rows), null);
        assert.equal(rowProblem({ ...saved(), value: null }, rows), 'required');
        assert.equal(rowProblem({ ...saved(), value: 95 }, rows), 'percent_range');
        assert.equal(rowProblem(typed(saved(), 'final', 230_000), rows), 'required');
        assert.equal(rowProblem(typed(saved(), 'off', 220_000), rows), 'not_below_price');
        assert.equal(rowProblem({ ...saved(), maxPerOrder: 0 }, rows), 'limit_range');
        assert.equal(rowProblem({ ...saved(), quantityLimit: 0 }, rows), 'quantity_range');
        assert.equal(rowProblem({ ...saved(), endsAt: '' }, rows), 'dates_required');
        assert.equal(rowProblem({ ...saved(), endsAt: '2026-10-19T00:00' }, rows), 'dates_order');
    });

    it('refuses rows of one variant whose dates overlap, but not back-to-back ones', () => {
        const first = saved();
        const overlapping = {
            ...saved(),
            key: 'n1',
            id: null,
            startsAt: '2026-10-25T00:00',
            endsAt: '2026-11-05T00:00',
        };
        const next = { ...overlapping, key: 'n2', startsAt: '2026-10-30T23:59' };

        assert.equal(rowProblem(overlapping, [first, overlapping]), 'overlap');
        assert.equal(rowProblem(next, [first, next]), null);
        assert.equal(rowProblem({ ...overlapping, variantId: 4 }, [first, { ...overlapping, variantId: 4 }]), null);
    });
});

describe('bulk edit', () => {
    it('changes only the chosen fields of the chosen rows', () => {
        const rows = [saved(), { ...saved(), key: 'p8', id: 8 }];
        const { rows: next } = applyBulk(rows, new Set(['p8']), {
            maxPerOrder: null,
            startsAt: '2026-11-01T00:00',
            endsAt: '2026-11-07T00:00',
        });

        assert.equal(next[0].maxPerOrder, 2);
        assert.equal(next[1].maxPerOrder, null);
        assert.equal(next[1].quantityLimit, 50);
        assert.equal(next[1].startsAt, '2026-11-01T00:00');
    });

    it('takes an off price from each row’s own price and skips rows it can’t apply to', () => {
        const cheap = { ...saved(), key: 'p8', id: 8, price: 100_000 };
        const { rows, skipped } = applyBulk([saved(), cheap], new Set(['p7', 'p8']), {
            discount: { field: 'final', value: 150_000 },
            maxPerOrder: 5,
        });

        assert.deepEqual(rowValues(rows[0], 1).final, 150_000);
        assert.deepEqual(skipped, ['p8']);
        assert.equal(rows[1].value, 20);
        assert.equal(rows[1].maxPerOrder, 5);
    });
});

describe('server errors', () => {
    it('finds the row of each error by its place in what was sent', () => {
        const sent = [saved(), { ...saved(), key: 'n1' }];
        const errors = mapErrors(sent, { 'promotions.1.starts_at': ['Overlaps'], deleted: ['x'] });

        assert.deepEqual([...errors], [['n1', 'Overlaps']]);
    });
});
