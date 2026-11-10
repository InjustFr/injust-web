const EUROS = /^(\d+)(?:[.,](\d{1,2}))?$/;

export function eurosToCents(value) {
    const match = EUROS.exec(String(value).trim());
    if (!match) return null;
    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

export function centsToEuros(cents) {
    if (cents === null) return '';
    const rest = cents % 100;
    return rest === 0 ? String(cents / 100) : `${Math.floor(cents / 100)}.${String(rest).padStart(2, '0')}`;
}

export const pair = (value) => ({ fr: value.fr, en: value.en });

export const trimmed = (value) => ({ fr: value.fr.trim(), en: value.en.trim() });

export const optionalInt = (value) => (String(value ?? '').trim() === '' ? null : Number(value));

export function moveId(ids, id, delta) {
    const from = ids.indexOf(id);
    const to = from + delta;
    if (from === -1 || to < 0 || to >= ids.length) return [...ids];
    const next = [...ids];
    next.splice(from, 1);
    next.splice(to, 0, id);
    return next;
}
