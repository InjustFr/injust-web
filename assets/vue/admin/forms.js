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
