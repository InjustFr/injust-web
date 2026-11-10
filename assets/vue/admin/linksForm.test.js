import { describe, expect, it } from 'vitest';
import { hostOf, linkForm, linkPayload } from './linksForm.js';

const link = { id: 'A', label: { fr: 'Écouter', en: 'Listen' }, url: 'https://open.spotify.com/artist/x', icon: 'spotify', highlighted: true, visible: false, clicks: 3, position: 0 };

describe('link form', () => {
    it('starts blank and visible', () => {
        expect(linkForm(null)).toEqual({ label: { fr: '', en: '' }, url: '', icon: '', highlighted: false, visible: true });
    });

    it('copies a link without sharing objects', () => {
        const form = linkForm(link);
        form.label.fr = 'x';
        expect(link.label.fr).toBe('Écouter');
        expect(linkForm(link)).toMatchObject({ icon: 'spotify', highlighted: true, visible: false });
        expect(linkForm({ ...link, icon: null }).icon).toBe('');
    });

    it('builds a trimmed payload with a null icon for none', () => {
        const form = { label: { fr: ' a ', en: ' b ' }, url: ' https://x.y ', icon: '', highlighted: true, visible: false };
        expect(linkPayload(form)).toEqual({ label: { fr: 'a', en: 'b' }, url: 'https://x.y', icon: null, highlighted: true, visible: false });
        expect(linkPayload({ ...form, icon: 'spotify' }).icon).toBe('spotify');
    });
});

describe('display helpers', () => {
    it('shows the host without www', () => {
        expect(hostOf('https://www.example.com/a?b=1')).toBe('example.com');
        expect(hostOf('https://open.spotify.com/x')).toBe('open.spotify.com');
        expect(hostOf('nope')).toBe('nope');
    });
});
