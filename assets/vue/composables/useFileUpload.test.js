import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
vi.hoisted(() => {
    globalThis.document = { body: { dataset: {} }, getElementById: () => null, createElement: () => ({}) };
});

import { useFileUpload } from './useFileUpload.js';

class FakeXhr {
    static last = null;

    constructor() {
        this.upload = {};
        this.headers = {};
        FakeXhr.last = this;
    }

    open(method, url) {
        this.method = method;
        this.url = url;
    }

    setRequestHeader(name, value) {
        this.headers[name] = value;
    }

    send(body) {
        this.body = body;
    }

    abort() {
        this.aborted = true;
        this.onabort();
    }

    respond(status, payload) {
        this.status = status;
        this.responseText = payload === undefined ? '' : JSON.stringify(payload);
        this.onload();
    }
}

beforeEach(() => {
    vi.stubGlobal('XMLHttpRequest', FakeXhr);
    vi.stubGlobal('FormData', class { append(name, value) { this[name] = value; } });
    vi.stubGlobal('window', { location: { assign: vi.fn() } });
});

afterEach(() => vi.unstubAllGlobals());

describe('useFileUpload', () => {
    const upload = (onDone = vi.fn()) => useFileUpload('/api/x', { onDone });

    it('walks idle, uploading, processing, done and calls onDone', async () => {
        const onDone = vi.fn();
        const { state, start } = upload(onDone);
        expect(state.value.status).toBe('idle');
        const finished = start({ name: 'a.mp3' });
        expect(state.value.status).toBe('uploading');
        FakeXhr.last.upload.onprogress({ lengthComputable: true, loaded: 1, total: 4 });
        expect(state.value.percent).toBe(25);
        FakeXhr.last.upload.onprogress({ lengthComputable: true, loaded: 4, total: 4 });
        expect(state.value.status).toBe('processing');
        FakeXhr.last.respond(200, { id: 'b1' });
        await expect(finished).resolves.toBe(true);
        expect(state.value.status).toBe('done');
        expect(onDone).toHaveBeenCalledOnce();
    });

    it('fails with the message and resolves false', async () => {
        const onDone = vi.fn();
        const { state, start } = upload(onDone);
        const finished = start({});
        FakeXhr.last.respond(422, { detail: 'Wrong format' });
        await expect(finished).resolves.toBe(false);
        expect(state.value).toEqual({ status: 'failed', percent: 0, error: 'Wrong format' });
        expect(onDone).not.toHaveBeenCalled();
    });

    it('cancels', async () => {
        const onDone = vi.fn();
        const { state, start, cancel } = upload(onDone);
        const finished = start({});
        cancel();
        await expect(finished).resolves.toBe(false);
        expect(state.value.status).toBe('cancelled');
        expect(onDone).not.toHaveBeenCalled();
    });

    it('does nothing when cancelling while idle and refuses a second start while busy', async () => {
        const { state, start, cancel } = upload();
        cancel();
        expect(state.value.status).toBe('idle');
        start({});
        const before = FakeXhr.last;
        await expect(start({})).resolves.toBe(false);
        expect(FakeXhr.last).toBe(before);
    });
});
