import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }));
vi.mock('./useApi.js', () => ({ useApi: () => api }));

let imageRatio;
let useImageFocus;
let useImageFraming;

class FakeImage {
    static last = null;

    constructor() {
        FakeImage.last = this;
    }

    set src(value) {
        this.url = value;
    }
}

const flush = () => new Promise((resolve) => setTimeout(resolve));

const urlOf = (id) => `/media/images/${id}.webp`;

beforeEach(async () => {
    vi.resetModules();
    ({ imageRatio, useImageFocus, useImageFraming } = await import('./useImageFraming.js'));
    vi.stubGlobal('Image', FakeImage);
    api.get.mockReset();
    api.put.mockReset();
});

afterEach(() => vi.unstubAllGlobals());

describe('imageRatio', () => {
    it('reads the natural ratio of an image', async () => {
        const ratio = imageRatio('/media/images/a.webp');
        expect(FakeImage.last.url).toBe('/media/images/a.webp');
        Object.assign(FakeImage.last, { naturalWidth: 1600, naturalHeight: 900 });
        FakeImage.last.onload();
        expect(await ratio).toBe(16 / 9);
    });

    it('gives null when the image does not load', async () => {
        const ratio = imageRatio('/media/images/b.webp');
        FakeImage.last.onerror();
        expect(await ratio).toBeNull();
    });
});

describe('useImageFraming', () => {
    async function upload(kind, width, height) {
        const framing = useImageFraming(kind, urlOf);
        const done = framing.uploaded('01UPLOADED');
        expect(FakeImage.last.url).toBe('/media/images/01UPLOADED.webp');
        Object.assign(FakeImage.last, { naturalWidth: width, naturalHeight: height });
        FakeImage.last.onload();
        await done;
        return framing;
    }

    it('starts closed', () => {
        expect(useImageFraming('cover', urlOf).state).toEqual({ kind: 'cover', open: false, imageId: null });
    });

    it('opens on demand for an image', () => {
        const framing = useImageFraming('cover', urlOf);
        framing.adjust('01ABC');
        expect(framing.state).toEqual({ kind: 'cover', open: true, imageId: '01ABC' });
    });

    it('opens after an upload that does not fit the slot', async () => {
        const framing = await upload('cover', 1600, 900);
        expect(framing.state).toEqual({ kind: 'cover', open: true, imageId: '01UPLOADED' });
    });

    it('stays closed after an upload that fits the slot', async () => {
        const framing = await upload('portrait', 800, 1000);
        expect(framing.state.open).toBe(false);
    });

    it('always opens after a hero upload', async () => {
        const framing = await upload('hero', 1600, 900);
        expect(framing.state.open).toBe(true);
    });

    it('does nothing when the upload left no image', async () => {
        const framing = useImageFraming('hero', urlOf);
        FakeImage.last = null;
        await framing.uploaded(null);
        expect(FakeImage.last).toBeNull();
        expect(framing.state.open).toBe(false);
    });
});

describe('useImageFocus', () => {
    it('lists every stored focus once and gives the CSS position of each image', async () => {
        api.get.mockResolvedValue([{ id: '01READ', x: 35, y: 60 }, { id: '01MORE', x: 0, y: 100 }]);
        const focus = useImageFocus();
        expect(focus.position('01READ')).toBeUndefined();
        expect(useImageFocus().position('01MORE')).toBeUndefined();
        await flush();
        expect(focus.position('01READ')).toBe('35% 60%');
        expect(focus.position('01MORE')).toBe('0% 100%');
        expect(focus.position('01NONE')).toBeUndefined();
        expect(api.get).toHaveBeenCalledTimes(1);
        expect(api.get).toHaveBeenCalledWith('/api/admin/images/focus');
    });

    it('has no position without an image', () => {
        expect(useImageFocus().position(null)).toBeUndefined();
        expect(api.get).not.toHaveBeenCalled();
    });

    it('keeps the centre when the focuses cannot be listed', async () => {
        api.get.mockRejectedValue(new Error('down'));
        const focus = useImageFocus();
        expect(focus.position('01FAIL')).toBeUndefined();
        await flush();
        expect(focus.position('01FAIL')).toBeUndefined();
        expect(api.get).toHaveBeenCalledTimes(1);
    });

    it('does not let a late list overwrite a focus saved meanwhile', async () => {
        let list;
        api.get.mockReturnValue(new Promise((resolve) => { list = resolve; }));
        api.put.mockResolvedValue({ id: '01RACE', x: 20, y: 30 });
        const focus = useImageFocus();
        focus.position('01RACE');
        await focus.save('01RACE', { x: 20, y: 30 });
        list([{ id: '01RACE', x: 90, y: 90 }]);
        await flush();
        expect(focus.position('01RACE')).toBe('20% 30%');
    });

    it('loads a focus and remembers it', async () => {
        api.get.mockResolvedValue({ id: '01LOAD', x: 10, y: 90 });
        const focus = useImageFocus();
        expect(await focus.load('01LOAD')).toEqual({ x: 10, y: 90 });
        expect(api.get).toHaveBeenCalledWith('/api/admin/images/01LOAD/focus');
        api.get.mockResolvedValue([]);
        expect(focus.position('01LOAD')).toBe('10% 90%');
    });

    it('saves a focus and remembers it', async () => {
        api.put.mockResolvedValue({ id: '01SAVE', x: 20, y: 30 });
        const focus = useImageFocus();
        await focus.save('01SAVE', { x: 20, y: 30 });
        expect(api.put).toHaveBeenCalledWith('/api/admin/images/01SAVE/focus', { x: 20, y: 30 });
        api.get.mockResolvedValue([]);
        expect(focus.position('01SAVE')).toBe('20% 30%');
        await flush();
        expect(focus.position('01SAVE')).toBe('20% 30%');
    });
});
