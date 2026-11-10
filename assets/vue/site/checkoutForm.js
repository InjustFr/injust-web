import { EMAIL, failure, length } from './siteForms.js';

export { failure };

const SERVICE = ['terms', 'early-start', 'pro-rata'];

const CONSENTS = { lesson: SERVICE, production: SERVICE, beat: ['terms', 'digital-waiver', 'licence'] };

const EMAIL_MAX = 180;

export function requiredConsents(productType) {
    return CONSENTS[productType] ?? [];
}

export function validEmail(email) {
    const trimmed = email.trim();

    return length(trimmed) <= EMAIL_MAX && EMAIL.test(trimmed);
}

export function ready(form, productType) {
    const kinds = requiredConsents(productType);

    return kinds.length > 0 && validEmail(form.email) && kinds.every((kind) => form.consents.includes(kind));
}

export function checkoutPayload(form, product) {
    return {
        productType: product.productType,
        productId: product.productId,
        locale: product.locale,
        email: form.email.trim(),
        consents: requiredConsents(product.productType).filter((kind) => form.consents.includes(kind)),
        cgvVersion: product.cgvVersion,
    };
}

