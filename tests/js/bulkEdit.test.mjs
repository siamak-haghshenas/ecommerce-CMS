// The bulk edit page's change tracking, checks, payload and error mapping (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    countChanges,
    discountPercent,
    mapErrors,
    problems,
    setValue,
    stockLevel,
    toPayload,
    valueOf,
} from '../../resources/js/admin/modules/products/bulkEdit.ts';

const variant = (overrides = {}) => ({
    id: 7,
    sku: 'SH-M',
    label: 'M',
    image: null,
    price: 200_000,
    sale_price: null,
    sale_ends_at: null,
    stock_quantity: 4,
    is_enabled: true,
    ...overrides,
});

const product = (variants = [variant()]) => ({
    id: 1,
    type: 'variable',
    name: 'Shirt',
    status: 'published',
    image: null,
    track_stock: true,
    low_stock_threshold: 5,
    variants,
});

describe('changes', () => {
    it('keeps only differences: typing the original value back removes the change', () => {
        const row = variant();
        let edits = setValue({}, row, 'price', 220_000);

        assert.equal(valueOf(edits, row, 'price'), 220_000);
        assert.equal(countChanges(edits), 1);

        edits = setValue(edits, row, 'price', 200_000);
        assert.deepEqual(edits, {});
    });

    it('flags an empty price or stock and a sale that is not lower than the price', () => {
        const row = variant();
        let edits = setValue({}, row, 'sale_price', 200_000);
        edits = setValue(edits, row, 'stock_quantity', null);

        assert.deepEqual(problems(edits, row), { sale_price: 'saleNotLower', stock_quantity: 'required' });
        assert.deepEqual(problems(setValue({}, row, 'price', null), row), { price: 'required' });
    });

    it('computes the discount of a valid sale only', () => {
        assert.equal(discountPercent(200_000, 150_000), 25);
        assert.equal(discountPercent(200_000, 200_000), null);
        assert.equal(discountPercent(200_000, null), null);
    });

    it('rates stock against the product threshold', () => {
        const tracked = product();

        assert.equal(stockLevel(tracked, 0), 'out');
        assert.equal(stockLevel(tracked, 5), 'low');
        assert.equal(stockLevel(tracked, 6), 'ok');
        assert.equal(stockLevel({ ...tracked, track_stock: false }, 0), 'untracked');
    });
});

describe('saving', () => {
    it('sends only changed fields, stock with the quantity the page loaded', () => {
        const first = variant();
        const second = variant({ id: 8 });
        let edits = setValue({}, first, 'stock_quantity', 9);
        edits = setValue(edits, second, 'is_enabled', false);
        edits = setValue(edits, second, 'sale_price', 150_000);

        assert.deepEqual(toPayload(edits, [product([first, second])]), [
            { id: 7, stock_quantity: 9, stock_quantity_was: 4 },
            { id: 8, is_enabled: false, sale_price: 150_000 },
        ]);
    });

    it('places server errors on their variant by the payload order', () => {
        const payload = [{ id: 7, price: 1 }, { id: 8, sale_price: 5 }, { id: 9, price: 2 }];

        assert.deepEqual(
            mapErrors(payload, {
                'variants.1.sale_price': ['Sale error'],
                'variants.0.stock_quantity_was': ['Stock error'],
                'variants.2.id': ['Deleted'],
            }),
            { 8: { sale_price: 'Sale error' }, 7: { stock_quantity: 'Stock error' }, 9: { price: 'Deleted' } },
        );
    });
});
