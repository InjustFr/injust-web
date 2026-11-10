import { centsToEuros, eurosToCents, optionalInt, pair, trimmed } from './forms.js';

export const KINDS = ['mix', 'master', 'mix-master', 'production', 'arrangement', 'recording', 'other'];
export const PRICINGS = ['fixed', 'quote'];

export function serviceForm(service) {
    if (!service) {
        return { kind: 'mix', title: { fr: '', en: '' }, description: { fr: '', en: '' }, pricing: 'fixed', price: '', deliveryDays: '', revisions: '' };
    }
    return {
        kind: service.kind,
        title: pair(service.title),
        description: pair(service.description),
        pricing: service.pricing,
        price: centsToEuros(service.price.cents),
        deliveryDays: service.deliveryDays ?? '',
        revisions: service.revisions ?? '',
    };
}

export function servicePayload(form) {
    const blankQuote = form.pricing === 'quote' && form.price.trim() === '';
    return {
        kind: form.kind,
        title: trimmed(form.title),
        description: trimmed(form.description),
        pricing: form.pricing,
        price: { cents: blankQuote ? 0 : eurosToCents(form.price) },
        deliveryDays: optionalInt(form.deliveryDays),
        revisions: optionalInt(form.revisions),
    };
}

export const portfolioForm = (item) => item
    ? { title: item.title, artist: item.artist, roles: [...item.roles], year: item.year ?? '', url: item.url ?? '' }
    : { title: '', artist: '', roles: [], year: '', url: '' };

export const portfolioPayload = (form) => ({
    title: form.title.trim(),
    artist: form.artist.trim(),
    roles: form.roles,
    year: optionalInt(form.year),
    url: form.url.trim() === '' ? null : form.url.trim(),
});

export const pageForm = (page) => ({ intro: pair(page.intro), process: pair(page.process), faq: pair(page.faq) });

export const pagePayload = (form) => ({ intro: trimmed(form.intro), process: trimmed(form.process), faq: trimmed(form.faq) });
