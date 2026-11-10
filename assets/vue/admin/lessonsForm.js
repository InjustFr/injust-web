import { centsToEuros, eurosToCents, pair, trimmed } from './forms.js';

export const KINDS = ['single', 'pack'];
export const FORMATS = ['at-home', 'at-teacher', 'online'];

export function sessionsFor(kind, sessions) {
    return kind === 'single' ? 1 : Math.max(2, Number.parseInt(sessions, 10) || 0);
}

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
