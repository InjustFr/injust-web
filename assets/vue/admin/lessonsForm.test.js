import { describe, expect, it } from 'vitest';
import {
    FORMATS,
    KINDS,
    centsToEuros,
    eurosToCents,
    offerForm,
    offerPayload,
    pageForm,
    pagePayload,
    sessionsFor,
} from './lessonsForm.js';

describe('euros', () => {
    it('parses whole, comma and dot amounts into cents', () => {
        expect(eurosToCents('45')).toBe(4500);
        expect(eurosToCents('45,50')).toBe(4550);
        expect(eurosToCents('45.5')).toBe(4550);
        expect(eurosToCents(' 0,05 ')).toBe(5);
        expect(eurosToCents('0')).toBe(0);
        expect(eurosToCents('19.99')).toBe(1999);
        expect(eurosToCents('1,005')).toBeNull();
    });

    it('refuses anything else', () => {
        expect(eurosToCents('')).toBeNull();
        expect(eurosToCents('abc')).toBeNull();
        expect(eurosToCents('-5')).toBeNull();
        expect(eurosToCents('4 5')).toBeNull();
        expect(eurosToCents('x45')).toBeNull();
        expect(eurosToCents('45x')).toBeNull();
        expect(eurosToCents('4,5,5')).toBeNull();
    });

    it('formats cents as a plain euro string', () => {
        expect(centsToEuros(4500)).toBe('45');
        expect(centsToEuros(4550)).toBe('45.50');
        expect(centsToEuros(5)).toBe('0.05');
        expect(centsToEuros(0)).toBe('0');
        expect(centsToEuros(null)).toBe('');
    });
});

describe('sessionsFor', () => {
    it('forces one session for a single', () => {
        expect(sessionsFor('single', 5)).toBe(1);
    });

    it('requires at least two for a pack', () => {
        expect(sessionsFor('pack', 1)).toBe(2);
        expect(sessionsFor('pack', 2)).toBe(2);
        expect(sessionsFor('pack', 10)).toBe(10);
        expect(sessionsFor('pack', 'x')).toBe(2);
    });
});

describe('offer form', () => {
    const offer = {
        id: 'a',
        title: { fr: 'Cours', en: 'Lesson' },
        description: { fr: 'd', en: 'e' },
        kind: 'pack',
        sessions: 5,
        format: 'online',
        minutes: 45,
        price: { cents: 20050 },
        position: 0,
    };

    it('starts from a single, one hour, at the teacher', () => {
        expect(offerForm(null)).toEqual({
            title: { fr: '', en: '' },
            description: { fr: '', en: '' },
            kind: 'single',
            sessions: 1,
            format: 'at-teacher',
            minutes: 60,
            price: '',
        });
    });

    it('loads an offer with the price in euros', () => {
        expect(offerForm(offer)).toEqual({
            title: { fr: 'Cours', en: 'Lesson' },
            description: { fr: 'd', en: 'e' },
            kind: 'pack',
            sessions: 5,
            format: 'online',
            minutes: 45,
            price: '200.50',
        });
    });

    it('builds the payload with cents and trimmed texts', () => {
        const form = offerForm(offer);
        form.title.fr = '  Cours  ';
        form.price = '45,5';
        form.minutes = '90';
        form.sessions = '3';
        expect(offerPayload(form)).toEqual({
            title: { fr: 'Cours', en: 'Lesson' },
            description: { fr: 'd', en: 'e' },
            kind: 'pack',
            sessions: 3,
            format: 'online',
            minutes: 90,
            price: { cents: 4550 },
        });
    });

    it('forces one session on a single and keeps an invalid price as null', () => {
        const form = offerForm(null);
        form.sessions = 4;
        form.price = 'abc';
        const payload = offerPayload(form);
        expect(payload.sessions).toBe(1);
        expect(payload.price).toEqual({ cents: null });
    });

    it('lists the options the API accepts', () => {
        expect(KINDS).toEqual(['single', 'pack']);
        expect(FORMATS).toEqual(['at-home', 'at-teacher', 'online']);
    });
});

describe('page form', () => {
    it('copies and trims both texts', () => {
        const page = { intro: { fr: 'a', en: 'b' }, faq: { fr: 'c', en: 'd' } };
        const form = pageForm(page);
        expect(form).toEqual(page);
        expect(form.intro).not.toBe(page.intro);
        form.faq.en = ' x ';
        expect(pagePayload(form)).toEqual({ intro: { fr: 'a', en: 'b' }, faq: { fr: 'c', en: 'x' } });
    });
});
