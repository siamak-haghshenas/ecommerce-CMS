// The shipping editor's ranges, checks, type switching and payload (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    addRate,
    changeType,
    emptyForm,
    feeRange,
    measureLimit,
    mergeCities,
    rateStarts,
    removeRate,
    shippingProblems,
    toShippingPayload,
} from '../../resources/js/admin/modules/shipping/shipping.ts';

const weightForm = (overrides = {}) => ({
    ...emptyForm('weight'),
    name: 'Post',
    rates: [
        { end: 1000, fee: 450_000 },
        { end: 3000, fee: 600_000 },
        { end: null, fee: 900_000 },
    ],
    ...overrides,
});

const city = (id, fee = null) => ({ id, name: `City ${id}`, province: { id: 1, name: 'Tehran' }, fee });

describe('ranges', () => {
    it('start each range where the previous one ends', () => {
        assert.deepEqual(rateStarts(weightForm().rates), [0, 1000, 3000]);
        assert.deepEqual(rateStarts([{ end: null, fee: 1 }, { end: 500, fee: 2 }]), [0, null]);
    });

    it('close a gap when a middle range is removed', () => {
        const rates = removeRate(weightForm().rates, 1);

        assert.deepEqual(rateStarts(rates), [0, 1000]);
        assert.equal(rates.length, 2);
    });

    it('add an empty last range', () => {
        assert.deepEqual(addRate([{ end: 1000, fee: 1 }]).at(-1), { end: null, fee: null });
    });
});

describe('checks', () => {
    it('accept a complete weight method', () => {
        assert.deepEqual(shippingProblems(weightForm()), {});
    });

    it('allow only the last range without an end, and ends above their start', () => {
        const problems = shippingProblems(
            weightForm({
                rates: [
                    { end: null, fee: 1 },
                    { end: 3000, fee: 2 },
                    { end: 3000, fee: null },
                ],
            }),
        );

        assert.deepEqual(problems, {
            'rates.0.end': 'endRequired',
            'rates.2.end': 'endAfterStart',
            'rates.2.fee': 'required',
        });
    });

    it('need a name, ranges, and delivery days in order', () => {
        assert.deepEqual(shippingProblems(weightForm({ name: ' ', rates: [], daysMin: 5, daysMax: 3 })), {
            name: 'required',
            rates: 'ratesRequired',
            delivery_days_max: 'daysOrder',
        });
    });

    it('need a default fee and cities for couriers, not ranges', () => {
        assert.deepEqual(shippingProblems({ ...emptyForm('courier'), name: 'Courier' }), {
            default_fee: 'required',
            cities: 'citiesRequired',
        });
        assert.deepEqual(
            shippingProblems({ ...emptyForm('courier'), name: 'Courier', defaultFee: 800_000, cities: [city(1)] }),
            {},
        );
    });
});

describe('type and payload', () => {
    it('start switched off', () => {
        assert.equal(emptyForm().isActive, false);
    });

    it('clear what belonged to the old type when switching', () => {
        const courier = changeType(weightForm({ packagingWeightGrams: 200 }), 'courier');

        assert.deepEqual(courier.rates, []);
        assert.equal(courier.packagingWeightGrams, null);

        const weight = changeType({ ...courier, defaultFee: 5, cities: [city(1)] }, 'volume');

        assert.deepEqual(weight.cities, []);
        assert.equal(weight.defaultFee, null);
        assert.deepEqual(weight.rates, [{ end: null, fee: null }]);
    });

    it('send ranges with their starts, and nothing of the other types', () => {
        const payload = toShippingPayload(weightForm({ feeOnDelivery: true, description: '  ' }));

        assert.deepEqual(payload.rates, [
            { start: 0, end: 1000, fee: 450_000 },
            { start: 1000, end: 3000, fee: 600_000 },
            { start: 3000, end: null, fee: 900_000 },
        ]);
        assert.equal(payload.cities, null);
        assert.equal(payload.default_fee, null);
        assert.equal(payload.fee_on_delivery, true);
        assert.equal(payload.description, null);
    });

    it('send courier cities with their own fee or null', () => {
        const payload = toShippingPayload({
            ...emptyForm('courier'),
            name: 'Courier',
            defaultFee: 800_000,
            packagingWeightGrams: 100,
            cities: [city(1), city(2, 1_200_000)],
        });

        assert.equal(payload.rates, null);
        assert.equal(payload.packaging_weight_grams, null);
        assert.deepEqual(payload.cities, [
            { id: 1, fee: null },
            { id: 2, fee: 1_200_000 },
        ]);
    });
});

describe('cities and summaries', () => {
    it('add only new cities, with the default fee', () => {
        const merged = mergeCities([city(1, 5)], [city(1, 9), city(2, 7)]);

        assert.deepEqual(
            merged.map((item) => [item.id, item.fee]),
            [
                [1, 5],
                [2, null],
            ],
        );
    });

    it('summarise the fees and the upper limit', () => {
        assert.deepEqual(
            feeRange({ type: 'courier', default_fee: 800, cities: [city(1), city(2, 1200)], rates: [] }),
            { min: 800, max: 1200 },
        );
        assert.deepEqual(feeRange({ type: 'weight', default_fee: null, cities: [], rates: [{ fee: 3 }, { fee: 1 }] }), {
            min: 1,
            max: 3,
        });
        assert.equal(measureLimit({ type: 'weight', rates: [{ end: 1000 }, { end: 20_000 }] }), 20_000);
        assert.equal(measureLimit({ type: 'weight', rates: [{ end: null }] }), null);
    });
});
