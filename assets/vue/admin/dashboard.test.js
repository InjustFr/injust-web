import { describe, expect, it } from 'vitest';
import { launchChecklist, latestMessages, sectionRows } from './dashboard.js';

const empty = { fr: '', en: '' };

function data(overrides = {}) {
    return {
        settings: {
            sections: { lessons: false, production: false, beats: false },
            onlineCheckout: false,
            contactEmail: '',
            legal: { complete: false },
            heroImage: null,
            searchIndexing: false,
            ...overrides.settings,
        },
        music: {
            releases: [],
            videos: [],
            socialLinks: [],
            text: { tagline: empty, manifesto: empty, bio: empty, hooks: [], portrait: null },
            ...overrides.music,
        },
        lessons: { offers: [], ...overrides.lessons },
        production: { services: [], portfolio: [], ...overrides.production },
        beats: { beats: [], tiers: [], ...overrides.beats },
    };
}

const done = (items) => Object.fromEntries(items.map((item) => [item.key, item.done]));

describe('launchChecklist', () => {
    it('lists every step undone on an empty site, each with the page that fixes it', () => {
        expect(launchChecklist(data())).toEqual([
            { key: 'heroImage', done: false, to: { name: 'settings' } },
            { key: 'featuredRelease', done: false, to: { name: 'music' } },
            { key: 'covers', done: false, to: { name: 'music' } },
            { key: 'bio', done: false, to: { name: 'music' } },
            { key: 'socialLinks', done: false, to: { name: 'music' } },
            { key: 'contactEmail', done: false, to: { name: 'settings' } },
            { key: 'legalIdentity', done: false, to: { name: 'settings' } },
            { key: 'searchIndexing', done: false, to: { name: 'settings' } },
        ]);
    });

    it('ticks every step once the site is ready', () => {
        const items = launchChecklist(data({
            settings: { heroImage: 'h', contactEmail: 'a@b.c', legal: { complete: true }, searchIndexing: true },
            music: {
                releases: [{ featured: true, cover: 'c1' }, { featured: false, cover: 'c2' }],
                socialLinks: [{ id: 1 }],
                text: { tagline: empty, manifesto: empty, bio: { fr: '', en: 'Bio' }, hooks: [], portrait: null },
            },
        }));

        expect(items.every((item) => item.done)).toBe(true);
    });

    it('needs a featured release and a cover on every release', () => {
        expect(done(launchChecklist(data({ music: { releases: [{ featured: false, cover: 'c' }] } })))).toMatchObject({ featuredRelease: false, covers: true });
        expect(done(launchChecklist(data({ music: { releases: [{ featured: true, cover: null }, { featured: false, cover: 'c' }] } })))).toMatchObject({ featuredRelease: true, covers: false });
    });

    it('takes the bio or the manifesto in either language, ignoring blanks', () => {
        const withText = (text) => done(launchChecklist(data({ music: { text: { ...data().music.text, ...text } } }))).bio;

        expect(withText({ manifesto: { fr: 'Texte', en: '' } })).toBe(true);
        expect(withText({ bio: { fr: '  ', en: '\n' } })).toBe(false);
    });

    it('ignores a contact email made of spaces', () => {
        expect(done(launchChecklist(data({ settings: { contactEmail: '   ' } }))).contactEmail).toBe(false);
    });
});

describe('sectionRows', () => {
    it('shows music always on, then each section with its item count, then checkout', () => {
        const rows = sectionRows(data({
            settings: { sections: { lessons: true, production: false, beats: false }, onlineCheckout: true },
            music: { releases: [{}, {}], videos: [{}] },
            lessons: { offers: [{}, {}, {}] },
            production: { services: [{}], portfolio: [{}, {}] },
            beats: { beats: [{}, {}, {}, {}] },
        }));

        expect(rows).toEqual([
            { key: 'music', enabled: true, count: 3, to: { name: 'music' } },
            { key: 'lessons', enabled: true, count: 3, to: { name: 'lessons' } },
            { key: 'production', enabled: false, count: 3, to: { name: 'production' } },
            { key: 'beats', enabled: false, count: 4, to: { name: 'beats' } },
            { key: 'checkout', enabled: true, count: null, to: { name: 'settings' } },
        ]);
    });
});

describe('latestMessages', () => {
    it('keeps the three newest open messages', () => {
        const messages = [
            { id: 'a', handled: false, receivedAt: '2026-11-01T10:00:00+01:00' },
            { id: 'b', handled: true, receivedAt: '2026-11-09T10:00:00+01:00' },
            { id: 'c', handled: false, receivedAt: '2026-11-08T10:00:00+01:00' },
            { id: 'd', handled: false, receivedAt: '2026-11-03T10:00:00+01:00' },
            { id: 'e', handled: false, receivedAt: '2026-11-05T10:00:00+01:00' },
        ];

        expect(latestMessages(messages).map((message) => message.id)).toEqual(['c', 'e', 'd']);
    });

    it('returns nothing when the inbox is empty', () => {
        expect(latestMessages([])).toEqual([]);
    });
});
