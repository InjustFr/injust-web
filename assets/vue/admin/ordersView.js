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
