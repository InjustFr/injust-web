export const LIMITS = { name: 100, email: 180, bodyMin: 10, bodyMax: 5000 };

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const length = (text) => [...text].length;

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

export function failure(status, problem, labels) {
    const violations = problem?.violations ?? [];
    if (violations.length > 0) {
        return { fields: Object.fromEntries(violations.map((violation) => [violation.propertyPath, violation.title])), message: labels.invalid };
    }
    const fallback = { 422: labels.invalid, 429: labels.tooMany }[status] ?? labels.error;

    return { fields: {}, message: problem?.detail ?? fallback };
}
