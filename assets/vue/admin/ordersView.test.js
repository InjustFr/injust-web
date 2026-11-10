import { describe, expect, it } from 'vitest';
import { REASON_MAX, STATUSES, canRefund, hasConflict, displayStatus, exportUrl, formatMoney, ordersUrl, proRataCents, refundPayload, remainingCents, thresholdProgress, totalsTable, yearOptions } from './ordersView.js';

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

describe('remainingCents', () => {
    it('is what was not refunded yet', () => {
        expect(remainingCents({ amountCents: 4500, refundedCents: 1500 })).toBe(3000);
        expect(remainingCents({ amountCents: 4500, refundedCents: 0 })).toBe(4500);
    });
});

describe('canRefund', () => {
    it('needs a paid or withdrawn order with something left', () => {
        expect(canRefund({ status: 'paid', amountCents: 100, refundedCents: 0 })).toBe(true);
        expect(canRefund({ status: 'withdrawn', amountCents: 100, refundedCents: 99 })).toBe(true);
        expect(canRefund({ status: 'withdrawn', amountCents: 100, refundedCents: 100 })).toBe(false);
        expect(canRefund({ status: 'refunded', amountCents: 100, refundedCents: 100 })).toBe(false);
        expect(canRefund({ status: 'pending', amountCents: 100, refundedCents: 0 })).toBe(false);
        expect(canRefund({ status: 'expired', amountCents: 100, refundedCents: 0 })).toBe(false);
    });
});

describe('displayStatus', () => {
    it('flags a partial refund on a paid or withdrawn order', () => {
        expect(displayStatus({ status: 'paid', refundedCents: 1 })).toBe('partiallyRefunded');
        expect(displayStatus({ status: 'withdrawn', refundedCents: 1 })).toBe('partiallyRefunded');
        expect(displayStatus({ status: 'paid', refundedCents: 0 })).toBe('paid');
        expect(displayStatus({ status: 'refunded', refundedCents: 100 })).toBe('refunded');
        expect(displayStatus({ status: 'withdrawn', refundedCents: 0 })).toBe('withdrawn');
        expect(displayStatus({ status: 'pending' })).toBe('pending');
    });
});

describe('proRataCents', () => {
    it('refunds the sessions not given yet, rounded to the cent', () => {
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'sessions', done: 1, total: 3 })).toBe(3000);
        expect(proRataCents({ amountCents: 1000, remainingCents: 1000, mode: 'sessions', done: 1, total: 3 })).toBe(667);
        expect(proRataCents({ amountCents: 1000, remainingCents: 1000, mode: 'sessions', done: 2, total: 3 })).toBe(333);
    });

    it('refunds a percentage of the price', () => {
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'percent', percent: 50 })).toBe(2250);
        expect(proRataCents({ amountCents: 999, remainingCents: 999, mode: 'percent', percent: 33 })).toBe(330);
    });

    it('never exceeds what remains and ignores impossible input', () => {
        expect(proRataCents({ amountCents: 4500, remainingCents: 1000, mode: 'percent', percent: 100 })).toBe(1000);
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'sessions', done: 5, total: 3 })).toBe(0);
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'sessions', done: 0, total: 0 })).toBe(0);
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'sessions', done: -1, total: 3 })).toBe(0);
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'percent', percent: 150 })).toBe(0);
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'percent', percent: 'x' })).toBe(0);
        expect(proRataCents({ amountCents: 4500, remainingCents: 4500, mode: 'none' })).toBe(0);
    });
});

describe('refundPayload', () => {
    it('caps the reason like the server does', () => {
        expect(REASON_MAX).toBe(500);
    });

    it('converts euros to cents and trims the reason', () => {
        expect(refundPayload('15,50', ' Pro rata ', 3000)).toEqual({ amountCents: 1550, reason: 'Pro rata' });
        expect(refundPayload('30', '', 3000)).toEqual({ amountCents: 3000, reason: '' });
    });

    it('refuses zero, junk and more than what remains', () => {
        expect(refundPayload('0', '', 3000)).toBeNull();
        expect(refundPayload('abc', '', 3000)).toBeNull();
        expect(refundPayload('30.01', '', 3000)).toBeNull();
        expect(refundPayload('', '', 3000)).toBeNull();
    });
});

describe('beat orders', () => {
    it('flags an exclusive conflict', () => {
        expect(hasConflict({ exclusiveConflictAt: '2026-11-10T10:00:00+01:00' })).toBe(true);
        expect(hasConflict({ exclusiveConflictAt: null })).toBe(false);
        expect(hasConflict({})).toBe(false);
    });
});
