import { EMAIL, failure, length } from './siteForms.js';

export { failure };

export const LIMITS = { name: 100, email: 180, bodyMin: 10, bodyMax: 5000 };

export function contactPayload(form) {
    return {
        name: form.name.trim(),
        email: form.email.trim(),
        topic: form.topic,
        offerRef: form.offer || null,
        body: form.body.trim(),
        website: form.website,
    };
}

function nameError(name) {
    if (name === '') return 'required';
    return length(name) > LIMITS.name ? 'tooLong' : null;
}

function emailError(email) {
    if (email === '') return 'required';
    if (length(email) > LIMITS.email) return 'tooLong';
    return EMAIL.test(email) ? null : 'invalid';
}

function bodyError(body) {
    if (length(body) < LIMITS.bodyMin) return 'tooShort';
    return length(body) > LIMITS.bodyMax ? 'tooLong' : null;
}

export function validate(payload) {
    const errors = { name: nameError(payload.name), email: emailError(payload.email), body: bodyError(payload.body) };

    return Object.fromEntries(Object.entries(errors).filter(([, error]) => error !== null));
}

