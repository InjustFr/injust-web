import { describe, expect, it } from 'vitest';
import {
    DELIVERABLES,
    FILE_ACCEPT,
    FILE_LIMITS,
    FILE_SLOTS,
    KEYS,
    beatForm,
    beatPayload,
    capsOf,
    exceedsLimit,
    fileChips,
    fileUrl,
    formatSize,
    keyLabel,
    pageForm,
    pagePayload,
    parseTags,
    slotFile,
    tierForm,
    tierPayload,
} from './beatsForm.js';

describe('musical keys', () => {
    it('lists the 24 keys the API accepts', () => {
        expect(KEYS).toHaveLength(24);
        expect(KEYS[0]).toBe('c-major');
        expect(KEYS).toContain('c-sharp-major');
        expect(KEYS).toContain('a-minor');
        expect(KEYS.at(-1)).toBe('b-minor');
    });

    it('labels them like the API', () => {
        expect(keyLabel('a-minor')).toBe('A minor');
        expect(keyLabel('c-sharp-major')).toBe('C# major');
        expect(keyLabel('f-sharp-minor')).toBe('F# minor');
    });
});

describe('parseTags', () => {
    it('splits on commas, trims, lowercases and drops blanks and duplicates', () => {
        expect(parseTags('trap, Dark ,808,, dark, ')).toEqual(['trap', 'dark', '808']);
    });

    it('handles empty text', () => {
        expect(parseTags('')).toEqual([]);
        expect(parseTags('  , ')).toEqual([]);
    });
});

describe('beat form', () => {
    const beat = { id: 'b1', title: 'Night Ride', slug: 'night-ride', bpm: 140, key: 'a-minor', tags: ['trap', 'dark'], visible: true, cover: null };

    it('starts empty, hidden, in A minor', () => {
        expect(beatForm(null)).toEqual({ title: '', slug: '', bpm: '', key: 'a-minor', tags: '', visible: false });
    });

    it('loads a beat with tags as text', () => {
        expect(beatForm(beat)).toEqual({ title: 'Night Ride', slug: 'night-ride', bpm: 140, key: 'a-minor', tags: 'trap, dark', visible: true });
    });

    it('builds the payload', () => {
        expect(beatPayload({ title: ' Night Ride ', slug: ' ', bpm: '140', key: 'a-minor', tags: 'Trap, dark, 808', visible: false })).toEqual({
            title: 'Night Ride',
            slug: null,
            bpm: 140,
            key: 'a-minor',
            tags: ['trap', 'dark', '808'],
            visible: false,
        });
    });

    it('keeps a typed slug and leaves a blank bpm for the API to refuse', () => {
        const payload = beatPayload({ title: 'x', slug: ' my-slug ', bpm: '', key: 'c-major', tags: '', visible: true });
        expect(payload.slug).toBe('my-slug');
        expect(payload.bpm).toBeNull();
    });
});

describe('tier form', () => {
    const tier = {
        id: 't1',
        name: { fr: 'Basique', en: 'Basic' },
        price: { cents: 2950 },
        summary: { fr: 'f', en: 'e' },
        deliverables: ['mp3', 'wav'],
        exclusive: false,
        streamCap: 50000,
        copyCap: null,
        videoCap: 1,
    };

    it('lists the deliverables in delivery order', () => {
        expect(DELIVERABLES).toEqual(['mp3', 'wav', 'stems']);
    });

    it('starts empty', () => {
        expect(tierForm(null)).toEqual({
            name: { fr: '', en: '' },
            summary: { fr: '', en: '' },
            price: '',
            deliverables: ['mp3'],
            exclusive: false,
            streamCap: '',
            copyCap: '',
            videoCap: '',
        });
    });

    it('loads a tier with euros and blank caps for no limit', () => {
        expect(tierForm(tier)).toEqual({
            name: { fr: 'Basique', en: 'Basic' },
            summary: { fr: 'f', en: 'e' },
            price: '29.50',
            deliverables: ['mp3', 'wav'],
            exclusive: false,
            streamCap: 50000,
            copyCap: '',
            videoCap: 1,
        });
    });

    it('builds the payload with cents, ordered deliverables and null caps', () => {
        expect(
            tierPayload({
                name: { fr: ' Basique ', en: 'Basic' },
                summary: { fr: '', en: ' e ' },
                price: '29,5',
                deliverables: ['stems', 'mp3'],
                exclusive: true,
                streamCap: '',
                copyCap: '2000',
                videoCap: ' ',
            }),
        ).toEqual({
            name: { fr: 'Basique', en: 'Basic' },
            summary: { fr: '', en: 'e' },
            price: { cents: 2950 },
            deliverables: ['mp3', 'stems'],
            exclusive: true,
            streamCap: null,
            copyCap: 2000,
            videoCap: null,
        });
    });

    it('sends null cents for an unreadable price so the API refuses it', () => {
        expect(tierPayload({ ...tierForm(null), price: 'abc' }).price).toEqual({ cents: null });
    });

    it('lists the caps that are set', () => {
        expect(capsOf(tier)).toEqual([
            ['streamCap', 50000],
            ['videoCap', 1],
        ]);
        expect(capsOf({ streamCap: null, copyCap: null, videoCap: null })).toEqual([]);
    });
});

describe('page form', () => {
    it('round-trips both languages and trims', () => {
        const page = { intro: { fr: 'a', en: 'b' }, faq: { fr: 'c', en: 'd' } };
        expect(pageForm(page)).toEqual(page);
        expect(pagePayload({ intro: { fr: ' a ', en: 'b' }, faq: { fr: 'c', en: ' d' } })).toEqual(page);
    });
});

describe('files', () => {
    const beat = {
        id: 'b1',
        preview: { id: 'p1', originalName: 'prev.mp3', size: 1200 },
        files: { mp3: { originalName: 'a.mp3', size: 5 }, wav: null, stems: null },
    };

    it('lists the slots, their limits and accepted types', () => {
        expect(FILE_SLOTS).toEqual(['preview', 'mp3', 'wav', 'stems']);
        expect(FILE_LIMITS).toEqual({ preview: 20971520, mp3: 52428800, wav: 524288000, stems: 2147483648 });
        expect(Object.keys(FILE_ACCEPT)).toEqual(FILE_SLOTS);
        expect(FILE_ACCEPT.stems).toContain('.zip');
    });

    it('builds the upload url of a slot', () => {
        expect(fileUrl('b1', 'preview')).toBe('/api/admin/beats/b1/preview');
        expect(fileUrl('b1', 'wav')).toBe('/api/admin/beats/b1/files/wav');
    });

    it('finds the file in a slot', () => {
        expect(slotFile(beat, 'preview')).toEqual(beat.preview);
        expect(slotFile(beat, 'mp3')).toEqual(beat.files.mp3);
        expect(slotFile(beat, 'wav')).toBeNull();
    });

    it('shows which files are there', () => {
        expect(fileChips(beat)).toEqual([
            { slot: 'preview', present: true },
            { slot: 'mp3', present: true },
            { slot: 'wav', present: false },
            { slot: 'stems', present: false },
        ]);
    });

    it('refuses a file over the limit of its slot but not one exactly at it', () => {
        expect(exceedsLimit('preview', { size: FILE_LIMITS.preview })).toBe(false);
        expect(exceedsLimit('preview', { size: FILE_LIMITS.preview + 1 })).toBe(true);
        expect(exceedsLimit('stems', { size: FILE_LIMITS.mp3 + 1 })).toBe(false);
    });

    it('formats sizes', () => {
        expect(formatSize(0)).toBe('0 B');
        expect(formatSize(512)).toBe('512 B');
        expect(formatSize(1024)).toBe('1 KB');
        expect(formatSize(1536)).toBe('1.5 KB');
        expect(formatSize(5 * 1024 * 1024)).toBe('5 MB');
        expect(formatSize(20971520 + 104858)).toBe('20.1 MB');
        expect(formatSize(2147483648)).toBe('2 GB');
        expect(formatSize(1536, 'fr')).toBe('1,5 KB');
    });
});
