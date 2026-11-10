import { describe, expect, it } from 'vitest';
import {
    KINDS,
    PRICINGS,
    optionalInt,
    pageForm,
    pagePayload,
    portfolioForm,
    portfolioPayload,
    servicePayload,
    serviceForm,
} from './productionForm.js';

describe('optionalInt', () => {
    it('turns blanks into null and keeps numbers', () => {
        expect(optionalInt('')).toBeNull();
        expect(optionalInt(null)).toBeNull();
        expect(optionalInt(' ')).toBeNull();
        expect(optionalInt('0')).toBe(0);
        expect(optionalInt(7)).toBe(7);
        expect(optionalInt('12')).toBe(12);
    });
});

describe('service form', () => {
    const service = {
        id: 'a',
        kind: 'mix',
        title: { fr: 'Mix', en: 'Mixing' },
        description: { fr: 'd', en: 'e' },
        pricing: 'fixed',
        price: { cents: 15050 },
        deliveryDays: 7,
        revisions: 0,
        position: 0,
    };

    it('lists the options the API accepts', () => {
        expect(KINDS).toEqual(['mix', 'master', 'mix-master', 'production', 'arrangement', 'recording', 'other']);
        expect(PRICINGS).toEqual(['fixed', 'quote']);
    });

    it('starts as a fixed mix with empty fields', () => {
        expect(serviceForm(null)).toEqual({
            kind: 'mix',
            title: { fr: '', en: '' },
            description: { fr: '', en: '' },
            pricing: 'fixed',
            price: '',
            deliveryDays: '',
            revisions: '',
        });
    });

    it('loads a service with euros and optional numbers', () => {
        expect(serviceForm(service)).toEqual({
            kind: 'mix',
            title: { fr: 'Mix', en: 'Mixing' },
            description: { fr: 'd', en: 'e' },
            pricing: 'fixed',
            price: '150.50',
            deliveryDays: 7,
            revisions: 0,
        });
        expect(serviceForm({ ...service, deliveryDays: null, revisions: null }).revisions).toBe('');
    });

    it('builds the payload with cents, trimmed texts and numbers', () => {
        const form = serviceForm(service);
        form.title.fr = ' Mix ';
        form.price = '99,9';
        form.deliveryDays = '10';
        form.revisions = '';
        expect(servicePayload(form)).toEqual({
            kind: 'mix',
            title: { fr: 'Mix', en: 'Mixing' },
            description: { fr: 'd', en: 'e' },
            pricing: 'fixed',
            price: { cents: 9990 },
            deliveryDays: 10,
            revisions: null,
        });
    });

    it('lets a quote leave the price blank as zero but not a fixed price', () => {
        const form = serviceForm(null);
        form.pricing = 'quote';
        expect(servicePayload(form).price).toEqual({ cents: 0 });
        form.price = '200';
        expect(servicePayload(form).price).toEqual({ cents: 20000 });
        form.pricing = 'fixed';
        form.price = '';
        expect(servicePayload(form).price).toEqual({ cents: null });
    });
});

describe('portfolio form', () => {
    const item = { id: 'p', title: 'Song', artist: 'Band', roles: ['mix', 'master'], year: 2024, cover: 'c', url: 'https://x.test', position: 0 };

    it('starts empty with no role', () => {
        expect(portfolioForm(null)).toEqual({ title: '', artist: '', roles: [], year: '', url: '' });
    });

    it('loads an item without its cover and copies the roles', () => {
        const form = portfolioForm(item);
        expect(form).toEqual({ title: 'Song', artist: 'Band', roles: ['mix', 'master'], year: 2024, url: 'https://x.test' });
        expect(form.roles).not.toBe(item.roles);
        expect(portfolioForm({ ...item, year: null, url: null })).toMatchObject({ year: '', url: '' });
    });

    it('builds the payload with trimmed text, null blanks and a numeric year', () => {
        const form = portfolioForm(item);
        form.title = ' Song 2 ';
        form.artist = ' B ';
        form.year = '2020';
        form.url = '  ';
        expect(portfolioPayload(form)).toEqual({ title: 'Song 2', artist: 'B', roles: ['mix', 'master'], year: 2020, url: null });
        form.url = ' https://y.test ';
        form.year = '';
        expect(portfolioPayload(form)).toMatchObject({ year: null, url: 'https://y.test' });
    });
});

describe('page form', () => {
    it('copies and trims the three texts', () => {
        const page = { intro: { fr: 'a', en: 'b' }, process: { fr: 'c', en: 'd' }, faq: { fr: 'e', en: 'f' } };
        const form = pageForm(page);
        expect(form).toEqual(page);
        expect(form.process).not.toBe(page.process);
        form.faq.en = ' x ';
        expect(pagePayload(form)).toEqual({ ...page, faq: { fr: 'e', en: 'x' } });
    });
});
