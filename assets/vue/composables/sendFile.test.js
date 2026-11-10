import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
vi.hoisted(() => {
    globalThis.document = { body: { dataset: {} }, getElementById: () => null, createElement: () => ({}) };
});

import { ApiError } from './useApi.js';
import { UploadCancelled, sendFile } from './sendFile.js';

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

describe('sendFile', () => {
    it('posts the file as the multipart field file and asks for json', () => {
        const file = { name: 'a.mp3' };
        sendFile('/api/x', file, () => {});
        expect(FakeXhr.last.method).toBe('POST');
        expect(FakeXhr.last.url).toBe('/api/x');
        expect(FakeXhr.last.headers).toEqual({ Accept: 'application/json' });
        expect(FakeXhr.last.body.file).toBe(file);
    });

    it('reports progress only when the length is known', () => {
        const onProgress = vi.fn();
        sendFile('/api/x', {}, onProgress);
        FakeXhr.last.upload.onprogress({ lengthComputable: false, loaded: 1, total: 0 });
        FakeXhr.last.upload.onprogress({ lengthComputable: true, loaded: 5, total: 10 });
        expect(onProgress).toHaveBeenCalledOnce();
        expect(onProgress).toHaveBeenCalledWith(5, 10);
    });

    it('resolves with the json payload', async () => {
        const { promise } = sendFile('/api/x', {}, () => {});
        FakeXhr.last.respond(200, { id: 'b1' });
        await expect(promise).resolves.toEqual({ id: 'b1' });
    });

    it('rejects with an ApiError carrying the problem detail and status', async () => {
        const { promise } = sendFile('/api/x', {}, () => {});
        FakeXhr.last.respond(422, { detail: 'Not an MP3 file.', violations: [{ propertyPath: 'file', title: 'bad' }] });
        const error = await promise.catch((caught) => caught);
        expect(error).toBeInstanceOf(ApiError);
        expect(error.message).toBe('Not an MP3 file.');
        expect(error.status).toBe(422);
        expect(error.fieldErrors).toEqual({ file: 'bad' });
    });

    it('falls back to the title then to a generic message', async () => {
        const first = sendFile('/api/x', {}, () => {});
        FakeXhr.last.respond(413, { title: 'Too large' });
        expect((await first.promise.catch((e) => e)).message).toBe('Too large');
        const second = sendFile('/api/x', {}, () => {});
        FakeXhr.last.respond(502);
        const error = await second.promise.catch((e) => e);
        expect(error).toBeInstanceOf(ApiError);
        expect(error.status).toBe(502);
        expect(error.message).not.toBe('');
    });

    it('sends a signed-out admin to the login', async () => {
        const { promise } = sendFile('/api/x', {}, () => {});
        FakeXhr.last.respond(401, { title: 'Unauthorized' });
        await promise.catch(() => {});
        expect(window.location.assign).toHaveBeenCalledWith('/login');
    });

    it('rejects with an ApiError on a network error', async () => {
        const { promise } = sendFile('/api/x', {}, () => {});
        FakeXhr.last.onerror();
        const error = await promise.catch((caught) => caught);
        expect(error).toBeInstanceOf(ApiError);
        expect(error.status).toBe(0);
    });

    it('aborts with UploadCancelled', async () => {
        const { promise, abort } = sendFile('/api/x', {}, () => {});
        abort();
        expect(FakeXhr.last.aborted).toBe(true);
        await expect(promise).rejects.toBeInstanceOf(UploadCancelled);
    });
});
