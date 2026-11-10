import { EMAIL, failure, length } from './siteForms.js';

export { failure };

export const LIMITS = { reference: 20, email: 180, name: 100, reason: 2000 };

const NEXT = { form: 'confirm', confirm: 'done' };

const PARIS = 'Europe/Paris';

const optional = (text) => text.trim() || null;

export function previewPayload(form) {
    return { reference: form.reference.trim().toUpperCase(), email: form.email.trim() };
}

export function withdrawalPayload(form, locale) {
    return { ...previewPayload(form), name: optional(form.name), reason: optional(form.reason), locale };
}

function referenceError(reference) {
    if (reference === '') return 'required';
    return length(reference) > LIMITS.reference ? 'tooLong' : null;
}

function emailError(email) {
    if (email === '') return 'required';
    if (length(email) > LIMITS.email) return 'tooLong';
    return EMAIL.test(email) ? null : 'invalid';
}

const tooLong = (text, max) => (length(text.trim()) > max ? 'tooLong' : null);

export function validate(form) {
    const { reference, email } = previewPayload(form);
    const errors = {
        reference: referenceError(reference),
        email: emailError(email),
        name: tooLong(form.name, LIMITS.name),
        reason: tooLong(form.reason, LIMITS.reason),
    };

    return Object.fromEntries(Object.entries(errors).filter(([, error]) => error !== null));
}

export function settle(step, status, body, labels) {
    if ([200, 201].includes(status) && body) {
        return { step: NEXT[step], data: body, fields: {}, message: '' };
    }
    const { fields, message } = failure(status, body, labels);

    return { step: Object.keys(fields).length > 0 ? 'form' : step, data: null, fields, message };
}

export function parisDate(iso, locale) {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeZone: PARIS }).format(new Date(iso));
}

export function parisTime(iso, locale) {
    return new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: PARIS }).format(new Date(iso));
}

export function calendarDay(day, locale) {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${day}T00:00:00Z`));
}

export function money(cents, locale) {
    return new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

export function fill(template, values) {
    return template.replace(/\{(\w+)\}/g, (placeholder, key) => values[key] ?? placeholder);
}
