import { describe, expect, it } from 'vitest';
import { calendarDay, failure, fill, money, parisDate, parisTime, previewPayload, settle, validate, withdrawalPayload } from './withdrawalForm.js';

const labels = { invalid: 'Check the form.', tooMany: 'Too many.', error: 'Failed.' };
const form = { reference: ' inj-ab12cd ', email: ' Joe@Example.test ', name: ' Joe ', reason: ' Changed my mind. ' };

describe('payloads', () => {
    it('looks the order up by its trimmed, upper-cased reference and the trimmed email', () => {
        expect(previewPayload(form)).toEqual({ reference: 'INJ-AB12CD', email: 'Joe@Example.test' });
    });

    it('sends the optional name and reason trimmed, with the page locale', () => {
        expect(withdrawalPayload(form, 'fr')).toEqual({ reference: 'INJ-AB12CD', email: 'Joe@Example.test', name: 'Joe', reason: 'Changed my mind.', locale: 'fr' });
    });

    it('sends null for a blank name or reason', () => {
        expect(withdrawalPayload({ ...form, name: '  ', reason: '' }, 'en')).toMatchObject({ name: null, reason: null });
    });
});

describe('validate', () => {
    it('accepts a complete form', () => {
        expect(validate(form)).toEqual({});
        expect(validate({ ...form, name: '', reason: '' })).toEqual({});
    });

    it('asks for the reference and the email', () => {
        expect(validate({ reference: ' ', email: '', name: '', reason: '' })).toEqual({ reference: 'required', email: 'required' });
    });

    it('checks the email shape and every length limit', () => {
        expect(validate({ ...form, email: 'joe@example' })).toEqual({ email: 'invalid' });
        expect(validate({ ...form, email: 'joe@example.test x' })).toEqual({ email: 'invalid' });
        expect(validate({ ...form, email: 'x y@example.test' })).toEqual({ email: 'invalid' });
        expect(validate({ ...form, email: `${'a'.repeat(171)}@punk.test` })).toEqual({ email: 'tooLong' });
        expect(validate({ ...form, email: `${'a'.repeat(170)}@punk.test` })).toEqual({});
        expect(validate({ ...form, reference: 'X'.repeat(21) })).toEqual({ reference: 'tooLong' });
        expect(validate({ ...form, reference: 'X'.repeat(20) })).toEqual({});
        expect(validate({ ...form, name: 'é'.repeat(101), reason: 'é'.repeat(2001) })).toEqual({ name: 'tooLong', reason: 'tooLong' });
        expect(validate({ ...form, name: ` ${'é'.repeat(100)} `, reason: ` ${'é'.repeat(2000)} ` })).toEqual({});
    });
});

describe('failure', () => {
    it('points at each field the server refused', () => {
        const problem = { violations: [{ propertyPath: 'email', title: 'Bad email.' }, { propertyPath: 'reason', title: 'Too long.' }] };

        expect(failure(422, problem, labels)).toEqual({ fields: { email: 'Bad email.', reason: 'Too long.' }, message: 'Check the form.' });
    });

    it('shows the rule the server gave, such as the end of the period or no matching order', () => {
        expect(failure(422, { detail: 'The withdrawal period ended on November 24, 2026.' }, labels)).toEqual({ fields: {}, message: 'The withdrawal period ended on November 24, 2026.' });
    });

    it('falls back on a message per status', () => {
        expect(failure(422, null, labels).message).toBe('Check the form.');
        expect(failure(429, {}, labels).message).toBe('Too many.');
        expect(failure(429, { detail: 'Wait 10 minutes.' }, labels).message).toBe('Wait 10 minutes.');
        expect(failure(500, null, labels).message).toBe('Failed.');
        expect(failure(0, null, labels).message).toBe('Failed.');
    });
});

describe('settle', () => {
    const preview = { reference: 'INJ-AB12CD' };

    it('moves from the form to the confirmation, then to the receipt', () => {
        expect(settle('form', 200, preview, labels)).toEqual({ step: 'confirm', data: preview, fields: {}, message: '' });
        expect(settle('confirm', 201, { receivedAt: 'x' }, labels)).toEqual({ step: 'done', data: { receivedAt: 'x' }, fields: {}, message: '' });
    });

    it('stays on the step with the message when the server refuses', () => {
        expect(settle('confirm', 422, { detail: 'Period over.' }, labels)).toEqual({ step: 'confirm', data: null, fields: {}, message: 'Period over.' });
        expect(settle('form', 429, null, labels)).toEqual({ step: 'form', data: null, fields: {}, message: 'Too many.' });
        expect(settle('confirm', 0, null, labels)).toEqual({ step: 'confirm', data: null, fields: {}, message: 'Failed.' });
    });

    it('goes back to the form when a field must be fixed', () => {
        const problem = { violations: [{ propertyPath: 'name', title: 'Too long.' }] };

        expect(settle('confirm', 422, problem, labels)).toEqual({ step: 'form', data: null, fields: { name: 'Too long.' }, message: 'Check the form.' });
    });

    it('never moves on without a body or on another success status', () => {
        expect(settle('form', 200, null, labels)).toEqual({ step: 'form', data: null, fields: {}, message: 'Failed.' });
        expect(settle('form', 204, preview, labels)).toEqual({ step: 'form', data: null, fields: {}, message: 'Failed.' });
        expect(settle('form', 300, preview, labels)).toEqual({ step: 'form', data: null, fields: {}, message: 'Failed.' });
    });
});

describe('dates', () => {
    it('dates and times the receipt in Paris, winter and summer', () => {
        expect([parisDate('2026-11-12T09:30:00+00:00', 'en'), parisTime('2026-11-12T09:30:00+00:00', 'en')]).toEqual(['November 12, 2026', '10:30']);
        expect([parisDate('2026-07-01T22:30:00+00:00', 'fr'), parisTime('2026-07-01T22:30:00+00:00', 'fr')]).toEqual(['2 juillet 2026', '00:30']);
    });

    it('shows the last day of the period as a calendar day', () => {
        expect(calendarDay('2026-11-24', 'en')).toBe('November 24, 2026');
        expect(calendarDay('2026-03-29', 'fr')).toBe('29 mars 2026');
    });

    it('formats the amount in euros', () => {
        expect(money(4500, 'en')).toBe('€45.00');
        expect(money(12050, 'fr')).toBe('120,50 €');
    });

    it('fills a translated sentence', () => {
        expect(fill('Received on {date} at {time}.', { date: 'November 12, 2026', time: '10:30' })).toBe('Received on November 12, 2026 at 10:30.');
        expect(fill('Hello {name}, {name}.', { name: 'Joe' })).toBe('Hello Joe, Joe.');
        expect(fill('Kept {unknown}.', {})).toBe('Kept {unknown}.');
    });
});
