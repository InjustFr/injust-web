import { describe, expect, it } from 'vitest';
import { checkoutPayload, failure, ready, requiredConsents, validEmail } from './checkoutForm.js';

const labels = { invalid: 'Check the form.', tooMany: 'Too many.', error: 'Failed.' };
const product = { productType: 'lesson', productId: '01JBN5Z7Q3X0000000000000AA', locale: 'fr', cgvVersion: 4 };
const all = ['terms', 'early-start', 'pro-rata'];

describe('requiredConsents', () => {
    it('asks for the terms, an early start and the pro-rata rule for a service', () => {
        expect(requiredConsents('lesson')).toEqual(all);
        expect(requiredConsents('production')).toEqual(all);
    });

    it('asks for the terms, the digital waiver and the licence for a beat', () => {
        expect(requiredConsents('beat')).toEqual(['terms', 'digital-waiver', 'licence']);
    });

    it('asks for nothing it does not know, so the order can never go through', () => {
        expect(requiredConsents('unknown')).toEqual([]);
    });
});

describe('ready', () => {
    it('lets the order go once every consent is ticked and the email is valid', () => {
        expect(ready({ email: ' joe@punk.test ', consents: ['pro-rata', 'terms', 'early-start'] }, 'lesson')).toBe(true);
    });

    it('waits for every required consent', () => {
        for (const missing of all) {
            expect(ready({ email: 'joe@punk.test', consents: all.filter((kind) => kind !== missing) }, 'production')).toBe(false);
        }
        expect(ready({ email: 'joe@punk.test', consents: ['terms', 'digital-waiver', 'licence'] }, 'lesson')).toBe(false);
    });

    it('waits for a valid email', () => {
        for (const email of ['', '   ', 'joe', 'joe@punk', 'jo e@punk.test', 'joe@punk.test x', `${'a'.repeat(171)}@punk.test`]) {
            expect(ready({ email, consents: all }, 'lesson')).toBe(false);
        }
        expect(ready({ email: `${'a'.repeat(170)}@punk.test`, consents: all }, 'lesson')).toBe(true);
    });

    it('never lets an unknown product type through', () => {
        expect(ready({ email: 'joe@punk.test', consents: all }, 'unknown')).toBe(false);
    });
});

describe('validEmail', () => {
    it('trims the address before checking it', () => {
        expect(validEmail('  joe@punk.test  ')).toBe(true);
        expect(validEmail('joe@punk')).toBe(false);
        expect(validEmail('')).toBe(false);
    });
});

describe('checkoutPayload', () => {
    it('sends the product, the trimmed email and the ticked consents in their legal order', () => {
        expect(checkoutPayload({ email: ' joe@punk.test ', consents: ['pro-rata', 'terms', 'early-start'] }, product)).toEqual({
            productType: 'lesson',
            productId: '01JBN5Z7Q3X0000000000000AA',
            locale: 'fr',
            email: 'joe@punk.test',
            consents: all,
            cgvVersion: 4,
        });
    });

    it('sends the licence tier of a beat', () => {
        const beat = { productType: 'beat', productId: '01JBN5Z7Q3X0000000000000BB', locale: 'en', cgvVersion: 2, tierId: '01JBN5Z7Q3X0000000000000CC', licenceVersion: 3 };

        expect(checkoutPayload({ email: 'joe@punk.test', consents: ['terms', 'digital-waiver', 'licence'] }, beat)).toEqual({
            productType: 'beat',
            productId: '01JBN5Z7Q3X0000000000000BB',
            locale: 'en',
            email: 'joe@punk.test',
            consents: ['terms', 'digital-waiver', 'licence'],
            cgvVersion: 2,
            tierId: '01JBN5Z7Q3X0000000000000CC',
            licenceVersion: 3,
        });
    });

    it('drops a ticked kind that the product does not ask for', () => {
        expect(checkoutPayload({ email: 'joe@punk.test', consents: ['terms', 'licence'] }, product).consents).toEqual(['terms']);
    });
});

describe('failure', () => {
    it('points at the email when the server refuses it', () => {
        const problem = { violations: [{ propertyPath: 'email', title: 'Bad email.' }] };

        expect(failure(422, problem, labels)).toEqual({ fields: { email: 'Bad email.' }, message: 'Check the form.' });
    });

    it('shows the business rule the server gave, such as a closed checkout or a missing consent', () => {
        expect(failure(422, { detail: 'Online checkout is closed.' }, labels)).toEqual({ fields: {}, message: 'Online checkout is closed.' });
        expect(failure(422, { violations: [] }, labels)).toEqual({ fields: {}, message: 'Check the form.' });
        expect(failure(422, null, labels)).toEqual({ fields: {}, message: 'Check the form.' });
    });

    it('asks the buyer to wait when the rate limit is hit', () => {
        expect(failure(429, { detail: 'Wait ten minutes.' }, labels)).toEqual({ fields: {}, message: 'Wait ten minutes.' });
        expect(failure(429, null, labels)).toEqual({ fields: {}, message: 'Too many.' });
    });

    it('falls back to a generic error when the payment provider or the network fails', () => {
        expect(failure(503, { detail: 'Payment unavailable.' }, labels)).toEqual({ fields: {}, message: 'Payment unavailable.' });
        expect(failure(500, null, labels)).toEqual({ fields: {}, message: 'Failed.' });
        expect(failure(0, undefined, labels)).toEqual({ fields: {}, message: 'Failed.' });
    });
});
