import { describe, expect, it } from 'vitest';
import {
    FORMATS,
    KINDS,
    offerForm,
    offerPayload,
    pageForm,
    pagePayload,
    sessionsFor,
} from './lessonsForm.js';

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
