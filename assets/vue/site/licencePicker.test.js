import { describe, expect, it } from 'vitest';
import { capLabel, licenceOptions } from './licencePicker.js';

const tiers = [
    { id: 'basic', name: 'Basic', price: '€29.99', deliverables: ['mp3'], streams: 10000, copies: null, videos: 1, exclusive: false },
    { id: 'premium', name: 'Premium', price: '€59', deliverables: ['mp3', 'wav'], streams: null, copies: null, videos: null, exclusive: false },
    { id: 'exclusive', name: 'Exclusive', price: '€499', deliverables: ['stems'], streams: null, copies: null, videos: null, exclusive: true },
];

describe('licenceOptions', () => {
    it('lists every tier in order with the checkout link of the ones this beat can be bought as', () => {
        const beat = { licences: { exclusive: '/fr/checkout/beat/b1?tier=exclusive', basic: '/fr/checkout/beat/b1?tier=basic', premium: null } };

        expect(licenceOptions(beat, tiers)).toEqual([
            { ...tiers[0], url: '/fr/checkout/beat/b1?tier=basic' },
            { ...tiers[1], url: null },
            { ...tiers[2], url: '/fr/checkout/beat/b1?tier=exclusive' },
        ]);
    });

    it('treats a tier the beat does not mention as unavailable, even when the server sent an empty list', () => {
        expect(licenceOptions({ licences: [] }, tiers).map((option) => option.url)).toEqual([null, null, null]);
    });
});

describe('capLabel', () => {
    it('formats a cap in the page locale', () => {
        expect(capLabel(100000, 'en', 'Unlimited')).toBe('100,000');
        expect(capLabel(100000, 'fr', 'Illimité')).toBe('100 000');
        expect(capLabel(1, 'fr', 'Illimité')).toBe('1');
    });

    it('says unlimited when there is no cap', () => {
        expect(capLabel(null, 'en', 'Unlimited')).toBe('Unlimited');
    });
});
