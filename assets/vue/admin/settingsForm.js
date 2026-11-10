export const LEGAL_KINDS = ['mentions', 'privacy', 'cgv-lessons', 'cgv-production', 'cgv-beats', 'licence'];

const IDENTITY_FIELDS = ['name', 'businessForm', 'siren', 'address', 'email', 'phone', 'publicationDirector', 'mediatorName', 'mediatorUrl', 'hostName', 'hostAddress', 'hostPhone'];

export function isSirenValid(value) {
    const digits = String(value).replace(/\s/g, '');
    if (!/^\d{9}$/.test(digits)) return false;
    const sum = [...digits].reduce((total, char, index) => {
        const digit = Number(char) * (index % 2 === 1 ? 2 : 1);
        return total + (digit > 9 ? digit - 9 : digit);
    }, 0);
    return sum % 10 === 0;
}

export function identityForm(legal) {
    return Object.fromEntries(IDENTITY_FIELDS.map((field) => [field, legal[field] ?? (field === 'businessForm' ? 'EI' : '')]));
}

export function identityPayload(form) {
    return Object.fromEntries(Object.entries(form).map(([field, value]) => [field, value.trim()]));
}

export function generalForm(settings) {
    return {
        contactEmail: settings.contactEmail,
        bookingUrl: settings.bookingUrl ?? '',
        vatMentions: { ...settings.vatMentions },
        seoTitle: { ...settings.seoTitle },
        seoDescription: { ...settings.seoDescription },
        searchIndexing: settings.searchIndexing,
        googleSiteVerification: settings.googleSiteVerification ?? '',
    };
}

export function generalPayload(form) {
    const bookingUrl = form.bookingUrl.trim();
    return { ...form, contactEmail: form.contactEmail.trim(), googleSiteVerification: form.googleSiteVerification.trim(), bookingUrl: bookingUrl === '' ? null : bookingUrl };
}

export function checkoutBlocked(settings) {
    return !settings.onlineCheckout && !settings.legal.complete;
}

export function imageUrl(ulid) {
    return ulid ? `/media/images/${ulid}.webp` : null;
}

export function legalPublicPath(kind, locale) {
    return `${locale === 'fr' ? '/fr' : ''}/legal/${kind}`;
}

export function legalDraft(document) {
    return { fr: document?.body.fr ?? '', en: document?.body.en ?? '' };
}

export function legalDraftChanged(document, draft) {
    const saved = legalDraft(document);
    return saved.fr !== draft.fr || saved.en !== draft.en;
}

export function identityErrors(form) {
    const siren = form.siren.trim();
    return siren === '' || isSirenValid(siren) ? {} : { siren: 'admin.settings.identity.sirenInvalid' };
}

export function imageUpload(files) {
    if (files.length === 0) return null;
    const body = new FormData();
    body.append('file', files[0]);
    return body;
}

export function formatDay(iso, intl) {
    return new Intl.DateTimeFormat(intl, { dateStyle: 'medium' }).format(new Date(iso));
}
