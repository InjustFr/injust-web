import { describe, expect, it } from 'vitest';
import { counter, googlePreview, PAGE_KEYS, seoForm, seoPayload } from './seoForm.js';

describe('counters', () => {
    it('flags too long for Google from 60 / 155 and refused past 70 / 170', () => {
        expect(counter('a'.repeat(60), 'title')).toEqual({ length: 60, max: 70, state: 'ok' });
        expect(counter('a'.repeat(61), 'title').state).toBe('long');
        expect(counter('a'.repeat(70), 'title').state).toBe('long');
        expect(counter('a'.repeat(71), 'title').state).toBe('over');
        expect(counter('a'.repeat(155), 'description')).toEqual({ length: 155, max: 170, state: 'ok' });
        expect(counter('a'.repeat(156), 'description').state).toBe('long');
        expect(counter('a'.repeat(171), 'description').state).toBe('over');
        expect(counter('', 'title').length).toBe(0);
    });
});

describe('seo form', () => {
    const page = { key: 'lessons', title: { fr: 'T', en: '' }, description: { fr: '', en: 'D' }, image: null };

    it('copies and trims', () => {
        const form = seoForm(page);
        form.title.fr = ' x ';
        expect(page.title.fr).toBe('T');
        expect(seoPayload(form)).toEqual({ title: { fr: 'x', en: '' }, description: { fr: '', en: 'D' } });
    });

    it('lists the three editable pages', () => {
        expect(PAGE_KEYS).toEqual(['lessons', 'production', 'contact']);
    });
});

describe('google preview', () => {
    it('truncates like Google with an ellipsis', () => {
        const preview = googlePreview({ title: 'a'.repeat(70), description: 'b'.repeat(200) });
        expect(preview.title).toBe(`${'a'.repeat(60)}…`);
        expect(preview.description).toBe(`${'b'.repeat(155)}…`);
        expect(googlePreview({ title: 'short', description: 'ok' })).toEqual({ title: 'short', description: 'ok' });
    });
});
