// The variant grid helpers of the product editor (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    combinations,
    comboKey,
    newRow,
    regenerate,
    rowFromVariant,
    suggestSku,
    toPayload,
} from '../../resources/js/admin/modules/products/variants.ts';

const variant = (overrides = {}) => ({
    id: 7,
    sku: 'TEE-S-RED',
    price: 1000,
    sale_price: null,
    sale_starts_at: null,
    sale_ends_at: null,
    current_price: 1000,
    discount_percent: 0,
    stock_quantity: 4,
    is_enabled: true,
    image_id: null,
    option_value_ids: [1, 10],
    ...overrides,
});

describe('combinations', () => {
    it('makes one combination per value of every option, in option order', () => {
        assert.deepEqual(combinations([[1, 2], [10, 20, 30]]), [
            [1, 10], [1, 20], [1, 30], [2, 10], [2, 20], [2, 30],
        ]);
    });

    it('has no combination when an option has no value', () => {
        assert.deepEqual(combinations([[1, 2], []]), []);
    });
});

describe('regenerate', () => {
    it('keeps existing variants (id, price, stock) and prices new ones like the first row', () => {
        const existing = rowFromVariant(variant());
        const rows = regenerate([existing], combinations([[1, 2], [10]]));

        assert.equal(rows.length, 2);
        assert.equal(rows[0], existing, 'the same combination keeps its row, whatever the value order');
        assert.equal(rows[1].id, null);
        assert.deepEqual(rows[1].optionValueIds, [2, 10]);
        assert.equal(rows[1].price, 1000);
        assert.equal(rows[1].stock, null, 'stock is not copied from an existing variant');
    });

    it('matches combinations regardless of value order', () => {
        assert.equal(comboKey([10, 1]), comboKey([1, 10]));
    });
});

describe('suggestSku', () => {
    it('builds an uppercase SKU from the slug and value slugs, dropping invalid characters', () => {
        assert.equal(suggestSku('cotton-shirt', ['red', 'xl']), 'COTTON-SHIRT-RED-XL');
        assert.equal(suggestSku('', ['red']), 'RED');
        assert.equal(suggestSku('a b', ['ü']), 'AB');
    });
});

describe('toPayload', () => {
    it('sends stock for an existing variant only when changed, with the quantity the admin saw', () => {
        const row = rowFromVariant(variant());

        assert.equal(toPayload(row, true).stock_quantity, null);

        row.stock = 9;
        const payload = toPayload(row, true);
        assert.equal(payload.stock_quantity, 9);
        assert.equal(payload.stock_quantity_was, 4);
    });

    it('sends the initial stock of a new variant, and no stock when it is not tracked', () => {
        const row = { ...newRow([1]), sku: ' NEW ', price: 5, stock: 3 };

        assert.equal(toPayload(row, true).stock_quantity, 3);
        assert.equal(toPayload(row, true).stock_quantity_was, null);
        assert.equal(toPayload(row, true).sku, 'NEW');
        assert.equal(toPayload(row, false).stock_quantity, null);
    });

    it('drops the sale dates when there is no sale price', () => {
        const row = { ...rowFromVariant(variant()), saleStartsAt: '2026-10-10T10:00' };

        assert.equal(toPayload(row, true).sale_starts_at, null);
        assert.notEqual(toPayload({ ...row, salePrice: 900 }, true).sale_starts_at, null);
    });
});
