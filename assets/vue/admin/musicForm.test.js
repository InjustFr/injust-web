import { describe, expect, it } from 'vitest';
import {
    formatDuration,
    hooksPayload,
    hooksText,
    parseDuration,
    parseTracks,
    releaseForm,
    releasePayload,
    textForm,
    textPayload,
} from './musicForm.js';

describe('durations', () => {
    it('parses mm:ss into seconds', () => {
        expect(parseDuration('3:45')).toBe(225);
        expect(parseDuration(' 10:05 ')).toBe(605);
        expect(parseDuration('0:00')).toBeNull();
    });

    it('refuses anything else', () => {
        expect(parseDuration('')).toBeNull();
        expect(parseDuration('abc')).toBeNull();
        expect(parseDuration('3:75')).toBeNull();
        expect(parseDuration('345')).toBeNull();
        expect(parseDuration('x3:45')).toBeNull();
        expect(parseDuration('3:456')).toBeNull();
    });

    it('formats seconds as m:ss', () => {
        expect(formatDuration(225)).toBe('3:45');
        expect(formatDuration(65)).toBe('1:05');
        expect(formatDuration(null)).toBe('');
    });
});

describe('parseTracks', () => {
    it('reads a pasted numbered list with durations', () => {
        expect(parseTracks('1. Riot Song 3:45\n2) Burn It Down (4:02)\n3 - No Time')).toEqual([
            { title: 'Riot Song', duration: '3:45' },
            { title: 'Burn It Down', duration: '4:02' },
            { title: 'No Time', duration: '' },
        ]);
    });

    it('skips blank lines and keeps titles that start with digits', () => {
        expect(parseTracks('\n  \n1999\n')).toEqual([{ title: '1999', duration: '' }]);
        expect(parseTracks('')).toEqual([]);
    });

    it('reads two-digit track numbers and long durations', () => {
        expect(parseTracks('12. Riot 10:05')).toEqual([{ title: 'Riot', duration: '10:05' }]);
    });
});

describe('releaseForm and releasePayload', () => {
    it('starts empty for a new release', () => {
        expect(releaseForm(null)).toEqual({
            title: '', slug: '', type: 'single', releaseDate: '', description: { fr: '', en: '' },
            tracks: [], links: [], smartLinkUrl: '', spotifyUrl: '',
        });
    });

    it('maps a view into a form and back', () => {
        const view = {
            title: 'Riot', slug: 'riot', type: 'ep', releaseDate: '2026-05-01',
            description: { fr: 'a', en: 'b' },
            tracks: [{ position: 1, title: 'One', durationSeconds: 225 }, { position: 2, title: 'Two', durationSeconds: null }],
            links: [{ platform: 'spotify', url: 'https://s.test' }],
            smartLinkUrl: 'https://ffm.to/x',
            spotifyEmbed: { url: 'https://open.spotify.com/album/abc' },
        };
        const form = releaseForm(view);
        expect(form.tracks).toEqual([{ title: 'One', duration: '3:45' }, { title: 'Two', duration: '' }]);
        expect(form.spotifyUrl).toBe('https://open.spotify.com/album/abc');
        expect(releasePayload(form)).toEqual({
            title: 'Riot', slug: 'riot', type: 'ep', releaseDate: '2026-05-01',
            description: { fr: 'a', en: 'b' },
            tracks: [{ title: 'One', durationSeconds: 225 }, { title: 'Two', durationSeconds: null }],
            links: [{ platform: 'spotify', url: 'https://s.test' }],
            smartLinkUrl: 'https://ffm.to/x',
            spotifyUrl: 'https://open.spotify.com/album/abc',
        });
    });

    it('trims, drops empty rows and nulls blank optional fields', () => {
        const payload = releasePayload({
            ...releaseForm(null),
            title: ' Riot ', slug: ' ', releaseDate: '2026-05-01',
            tracks: [{ title: ' ', duration: '' }, { title: ' A ', duration: 'x' }],
            links: [{ platform: 'other', url: ' ' }, { platform: 'deezer', url: ' https://d.test ' }],
        });
        expect(payload.title).toBe('Riot');
        expect(payload.slug).toBeNull();
        expect(payload.tracks).toEqual([{ title: 'A', durationSeconds: null }]);
        expect(payload.links).toEqual([{ platform: 'deezer', url: 'https://d.test' }]);
        expect(payload.smartLinkUrl).toBeNull();
        expect(payload.spotifyUrl).toBeNull();
    });

    it('trims the optional urls it keeps', () => {
        const payload = releasePayload({ ...releaseForm(null), smartLinkUrl: ' https://ffm.to/x ', spotifyUrl: ' spotify:album:abc ' });
        expect([payload.smartLinkUrl, payload.spotifyUrl]).toEqual(['https://ffm.to/x', 'spotify:album:abc']);
    });

    it('leaves the optional urls blank for a release without them', () => {
        const form = releaseForm({ ...releaseForm(null), description: { fr: '', en: '' }, tracks: [], links: [], smartLinkUrl: null, spotifyEmbed: null });
        expect([form.smartLinkUrl, form.spotifyUrl]).toEqual(['', '']);
    });
});

describe('texts', () => {
    it('round-trips hooks one per line', () => {
        expect(hooksText(['a', 'b'])).toBe('a\nb');
        expect(hooksPayload(' a \n\n b \n')).toEqual(['a', 'b']);
        expect(hooksPayload('no gods\nno masters')).toEqual(['no gods', 'no masters']);
    });

    it('maps the text view', () => {
        const view = { tagline: { fr: 'f', en: 'e' }, manifesto: { fr: '', en: 'm' }, bio: { fr: 'b', en: '' }, hooks: ['x', 'y'], portrait: null };
        const form = textForm(view);
        expect(form.hooks).toBe('x\ny');
        expect(textPayload(form)).toEqual({ tagline: { fr: 'f', en: 'e' }, manifesto: { fr: '', en: 'm' }, bio: { fr: 'b', en: '' }, hooks: ['x', 'y'] });
    });
});
