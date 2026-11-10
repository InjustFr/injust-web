import { describe, expect, it } from 'vitest';
import { contactPayload, failure, validate } from './contactForm.js';

const form = { name: ' Joe ', email: ' joe@punk.test ', topic: 'lessons', offer: '01JBN5Z7Q3X0000000000000AA', body: '  I want to play loud.  ', website: '' };
const labels = { invalid: 'Check the form.', tooMany: 'Too many.', error: 'Failed.' };
const valid = { name: 'Joe', email: 'joe@punk.test', topic: 'general', offerRef: null, body: 'I want to play loud.', website: '' };

describe('contactPayload', () => {
    it('trims the fields and keeps the offer for a lessons message', () => {
        expect(contactPayload(form)).toEqual({ name: 'Joe', email: 'joe@punk.test', topic: 'lessons', offerRef: '01JBN5Z7Q3X0000000000000AA', body: 'I want to play loud.', website: '' });
    });

    it('keeps the offer for any topic and lets the server resolve it', () => {
        expect(contactPayload({ ...form, topic: 'production' }).offerRef).toBe('01JBN5Z7Q3X0000000000000AA');
        expect(contactPayload({ ...form, topic: 'general' }).offerRef).toBe('01JBN5Z7Q3X0000000000000AA');
    });

    it('sends no offer when none is picked', () => {
        expect(contactPayload({ ...form, offer: null }).offerRef).toBeNull();
        expect(contactPayload({ ...form, offer: '' }).offerRef).toBeNull();
    });

    it('passes the honeypot through untouched', () => {
        expect(contactPayload({ ...form, website: ' bot ' }).website).toBe(' bot ');
    });
});

describe('validate', () => {
    it('accepts a complete message', () => {
        expect(validate(valid)).toEqual({});
    });

    it('requires a name and an email', () => {
        expect(validate({ ...valid, name: '', email: '' })).toEqual({ name: 'required', email: 'required' });
    });

    it('limits the name to 100 characters', () => {
        expect(validate({ ...valid, name: 'a'.repeat(100) })).toEqual({});
        expect(validate({ ...valid, name: 'a'.repeat(101) })).toEqual({ name: 'tooLong' });
        expect(validate({ ...valid, name: 'é'.repeat(100) })).toEqual({});
        expect(validate({ ...valid, name: '🎸'.repeat(100) })).toEqual({});
    });

    it('checks the email shape and limits it to 180 characters', () => {
        for (const email of ['joe', 'joe@', '@punk.test', 'joe@punk', 'jo e@punk.test', 'joe@pu nk.test', 'joe@@punk.test', 'joe@punk.test x']) {
            expect(validate({ ...valid, email })).toEqual({ email: 'invalid' });
        }
        const local = 'a'.repeat(170);
        expect(validate({ ...valid, email: `${local}@punk.test` })).toEqual({});
        expect(validate({ ...valid, email: `${local}a@punk.test` })).toEqual({ email: 'tooLong' });
    });

    it('wants a message of 10 to 5000 characters', () => {
        expect(validate({ ...valid, body: 'a'.repeat(9) })).toEqual({ body: 'tooShort' });
        expect(validate({ ...valid, body: 'a'.repeat(10) })).toEqual({});
        expect(validate({ ...valid, body: 'a'.repeat(5000) })).toEqual({});
        expect(validate({ ...valid, body: 'a'.repeat(5001) })).toEqual({ body: 'tooLong' });
        expect(validate({ ...valid, body: '' })).toEqual({ body: 'tooShort' });
    });
});

describe('failure', () => {
    it('maps the server violations to their fields', () => {
        const problem = { title: 'Validation Failed', violations: [{ propertyPath: 'email', title: 'Bad email.' }, { propertyPath: 'body', title: 'Too short.' }] };

        expect(failure(422, problem, labels)).toEqual({ fields: { email: 'Bad email.', body: 'Too short.' }, message: 'Check the form.' });
    });

    it('shows the rule the server broke when there is no field to point at', () => {
        expect(failure(422, { detail: 'Nope.' }, labels)).toEqual({ fields: {}, message: 'Nope.' });
        expect(failure(422, null, labels)).toEqual({ fields: {}, message: 'Check the form.' });
    });

    it('asks the visitor to wait when the rate limit is hit', () => {
        expect(failure(429, { detail: 'Wait ten minutes.' }, labels)).toEqual({ fields: {}, message: 'Wait ten minutes.' });
        expect(failure(429, null, labels)).toEqual({ fields: {}, message: 'Too many.' });
    });

    it('falls back to a generic error', () => {
        expect(failure(403, { detail: 'Refused.' }, labels)).toEqual({ fields: {}, message: 'Refused.' });
        expect(failure(500, null, labels)).toEqual({ fields: {}, message: 'Failed.' });
        expect(failure(0, undefined, labels)).toEqual({ fields: {}, message: 'Failed.' });
    });
});
