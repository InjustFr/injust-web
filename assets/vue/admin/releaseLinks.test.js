import { describe, expect, it } from 'vitest';
import { PUBLIC_ORDER, finderEnvKeys, hiddenAfter, isValidUpc, linkRows, missingFinders, normalizeUpc, reportKind } from './releaseLinks.js';

describe('upc', () => {
    it('accepts valid GTIN-12 and GTIN-13', () => {
        expect(isValidUpc('701518909440')).toBe(true);
        expect(isValidUpc('036000291452')).toBe(true);
        expect(isValidUpc('4006381333931')).toBe(true);
    });

    it('refuses a wrong check digit, a wrong length or non digits', () => {
        expect(isValidUpc('701518909441')).toBe(false);
        expect(isValidUpc('70151890944')).toBe(false);
        expect(isValidUpc('70151890944a')).toBe(false);
        expect(isValidUpc('')).toBe(false);
    });

    it('strips spaces and dashes', () => {
        expect(normalizeUpc(' 7015-1890 9440 ')).toBe('701518909440');
    });
});

describe('linkRows', () => {
    const release = {
        links: [{ platform: 'deezer', url: 'https://d', source: 'auto' }, { platform: 'bandcamp', url: 'https://b', source: 'manual' }],
        hiddenPlatforms: ['tidal'],
        clicks: { deezer: 4 },
    };
    const rows = linkRows(release);

    it('lists every platform in the public order', () => {
        expect(rows.map((row) => row.platform)).toEqual(PUBLIC_ORDER);
        expect(PUBLIC_ORDER).toHaveLength(10);
    });

    it('carries url, source, clicks and hidden', () => {
        expect(rows.find((row) => row.platform === 'deezer')).toEqual({ platform: 'deezer', url: 'https://d', source: 'auto', clicks: 4, hidden: false });
        expect(rows.find((row) => row.platform === 'bandcamp')).toMatchObject({ source: 'manual', clicks: 0 });
        expect(rows.find((row) => row.platform === 'tidal')).toMatchObject({ url: null, source: null, hidden: true });
        expect(rows.find((row) => row.platform === 'spotify')).toMatchObject({ url: null, hidden: false });
    });

    it('prefers a manual link over an auto one', () => {
        const both = linkRows({ links: [{ platform: 'deezer', url: 'a', source: 'auto' }, { platform: 'deezer', url: 'm', source: 'manual' }], hiddenPlatforms: [], clicks: {} });
        expect(both.find((row) => row.platform === 'deezer').url).toBe('m');
    });
});

describe('hiddenAfter', () => {
    it('adds and removes without duplicates', () => {
        expect(hiddenAfter(['tidal'], 'deezer', true)).toEqual(['tidal', 'deezer']);
        expect(hiddenAfter(['tidal'], 'tidal', true)).toEqual(['tidal']);
        expect(hiddenAfter(['tidal', 'deezer'], 'tidal', false)).toEqual(['deezer']);
    });
});

describe('report', () => {
    it('maps statuses to message kinds', () => {
        expect(reportKind('found')).toBe('success');
        expect(reportKind('notFound')).toBe('info');
        expect(reportKind('notConfigured')).toBe('info');
        expect(reportKind('failed')).toBe('error');
    });
});

describe('finders', () => {
    const finders = [{ platform: 'spotify', configured: false }, { platform: 'deezer', configured: true }, { platform: 'tidal', configured: false }];

    it('lists the unconfigured finders', () => {
        expect(missingFinders(finders).map((finder) => finder.platform)).toEqual(['spotify', 'tidal']);
    });

    it('names the env keys of a platform', () => {
        expect(finderEnvKeys('spotify')).toEqual(['SPOTIFY_CLIENT_ID', 'SPOTIFY_CLIENT_SECRET']);
        expect(finderEnvKeys('tidal')).toEqual(['TIDAL_CLIENT_ID', 'TIDAL_CLIENT_SECRET']);
    });
});
