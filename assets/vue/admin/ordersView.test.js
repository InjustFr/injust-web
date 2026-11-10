import { describe, expect, it } from 'vitest';
import { STATUSES, exportUrl, formatMoney, ordersUrl, thresholdProgress, totalsTable, yearOptions } from './ordersView.js';

describe('yearOptions', () => {
    it('lists the current year first then the previous ones', () => {
        expect(yearOptions(2026, 3)).toEqual([2026, 2025, 2024]);
    });
});

describe('ordersUrl', () => {
    it('adds only the chosen filters', () => {
        expect(ordersUrl({ status: '', year: 2026 })).toBe('/api/admin/orders?year=2026');
        expect(ordersUrl({ status: 'paid', year: 2026 })).toBe('/api/admin/orders?status=paid&year=2026');
        expect(ordersUrl({ status: '', year: null })).toBe('/api/admin/orders');
    });
});

describe('exportUrl', () => {
    it('targets the revenue book of the year', () => {
        expect(exportUrl(2026)).toBe('/api/admin/orders/export?year=2026');
    });
});

describe('STATUSES', () => {
    it('matches the order lifecycle', () => {
        expect(STATUSES).toEqual(['pending', 'paid', 'expired', 'withdrawn', 'refunded']);
    });
});

describe('formatMoney', () => {
    it('formats cents as euros in the given locale', () => {
        expect(formatMoney(4500, 'fr-FR').replace(/\s/g, ' ')).toBe('45,00 €');
        expect(formatMoney(4500, 'en-GB')).toBe('€45.00');
    });
});

describe('totalsTable', () => {
    const rows = [
        { country: 'DE', productType: 'lesson', amountCents: 1000, count: 2 },
        { country: 'DE', productType: 'production', amountCents: 500, count: 1 },
        { country: 'FR', productType: 'lesson', amountCents: 300, count: 1 },
    ];

    it('gathers the types of each country and sums them', () => {
        expect(totalsTable(rows)).toEqual({
            types: ['lesson', 'production'],
            rows: [
                { country: 'DE', cells: { lesson: 1000, production: 500 }, total: 1500 },
                { country: 'FR', cells: { lesson: 300 }, total: 300 },
            ],
            totals: { lesson: 1300, production: 500 },
            total: 1800,
        });
    });

    it('is empty without sales', () => {
        expect(totalsTable([])).toEqual({ types: [], rows: [], totals: {}, total: 0 });
    });
});

describe('thresholdProgress', () => {
    it('is a share of the threshold, capped at 100', () => {
        expect(thresholdProgress(5000, 10000)).toEqual({ percent: 50, state: 'ok' });
        expect(thresholdProgress(25000, 10000).percent).toBe(100);
    });

    it('warns from 80 percent and flags a crossed threshold', () => {
        expect(thresholdProgress(7999, 10000).state).toBe('ok');
        expect(thresholdProgress(8000, 10000).state).toBe('warning');
        expect(thresholdProgress(9999, 10000).state).toBe('warning');
        expect(thresholdProgress(10000, 10000).state).toBe('crossed');
    });
});
