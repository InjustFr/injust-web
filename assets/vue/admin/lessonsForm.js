export const KINDS = ['single', 'pack'];
export const FORMATS = ['at-home', 'at-teacher', 'online'];

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

export function sessionsFor(kind, sessions) {
    return kind === 'single' ? 1 : Math.max(2, Number.parseInt(sessions, 10) || 0);
}

const pair = (value) => ({ fr: value.fr, en: value.en });
const trimmed = (value) => ({ fr: value.fr.trim(), en: value.en.trim() });

export function offerForm(offer) {
    if (!offer) {
        return { title: { fr: '', en: '' }, description: { fr: '', en: '' }, kind: 'single', sessions: 1, format: 'at-teacher', minutes: 60, price: '' };
    }
    return {
        title: pair(offer.title),
        description: pair(offer.description),
        kind: offer.kind,
        sessions: offer.sessions,
        format: offer.format,
        minutes: offer.minutes,
        price: centsToEuros(offer.price.cents),
    };
}

export function offerPayload(form) {
    return {
        title: trimmed(form.title),
        description: trimmed(form.description),
        kind: form.kind,
        sessions: sessionsFor(form.kind, form.sessions),
        format: form.format,
        minutes: Number(form.minutes),
        price: { cents: eurosToCents(form.price) },
    };
}

export const pageForm = (page) => ({ intro: pair(page.intro), faq: pair(page.faq) });

export const pagePayload = (form) => ({ intro: trimmed(form.intro), faq: trimmed(form.faq) });
