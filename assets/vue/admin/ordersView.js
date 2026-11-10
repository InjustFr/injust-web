import { eurosToCents } from './forms.js';

export const STATUSES = ['pending', 'paid', 'expired', 'withdrawn', 'refunded'];

const WARNING_SHARE = 0.8;

export const yearOptions = (current, count = 5) => Array.from({ length: count }, (_, index) => current - index);

export function ordersUrl({ status, year }) {
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (year) params.set('year', year);
    const query = params.toString();
    return query ? `/api/admin/orders?${query}` : '/api/admin/orders';
}

export const exportUrl = (year) => `/api/admin/orders/export?year=${year}`;

export const formatMoney = (cents, intlLocale) => new Intl.NumberFormat(intlLocale, { style: 'currency', currency: 'EUR' }).format(cents / 100);

export function totalsTable(rows) {
    const types = [...new Set(rows.map((row) => row.productType))].sort();
    const byCountry = new Map();
    const totals = {};
    let total = 0;
    for (const { country, productType, amountCents } of rows) {
        const line = byCountry.get(country) ?? { country, cells: {}, total: 0 };
        line.cells[productType] = (line.cells[productType] ?? 0) + amountCents;
        line.total += amountCents;
        byCountry.set(country, line);
        totals[productType] = (totals[productType] ?? 0) + amountCents;
        total += amountCents;
    }
    return { types, rows: [...byCountry.values()], totals, total };
}

export function thresholdProgress(cents, threshold) {
    const state = cents >= threshold ? 'crossed' : cents >= threshold * WARNING_SHARE ? 'warning' : 'ok';
    return { percent: Math.min(100, Math.floor((cents / threshold) * 100)), state };
}

export const remainingCents = (order) => order.amountCents - order.refundedCents;

export const canRefund = (order) => (order.status === 'paid' || order.status === 'withdrawn') && remainingCents(order) > 0;

export const displayStatus = (order) => ((order.status === 'paid' || order.status === 'withdrawn') && order.refundedCents > 0 ? 'partiallyRefunded' : order.status);

export function proRataCents({ amountCents, remainingCents: remaining, mode, done, total, percent }) {
    let share = 0;
    if (mode === 'sessions' && total > 0 && done >= 0 && done <= total) share = (total - done) / total;
    if (mode === 'percent' && percent >= 0 && percent <= 100) share = percent / 100;
    return Math.min(remaining, Math.round(amountCents * share));
}

export const REASON_MAX = 500;

export function refundPayload(euros, reason, remaining) {
    const amountCents = eurosToCents(euros);
    return amountCents === null || amountCents <= 0 || amountCents > remaining ? null : { amountCents, reason: reason.trim() };
}
