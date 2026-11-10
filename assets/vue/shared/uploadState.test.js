import { describe, expect, it } from 'vitest';
import { idle, isBusy, reduce } from './uploadState.js';

const uploading = (percent = 0) => ({ status: 'uploading', percent, error: null });

describe('upload state', () => {
    it('starts idle', () => {
        expect(idle()).toEqual({ status: 'idle', percent: 0, error: null });
    });

    it('starts uploading from any resting state', () => {
        ['idle', 'done', 'failed', 'cancelled'].forEach((status) => {
            expect(reduce({ status, percent: 80, error: 'x' }, { type: 'start' })).toEqual(uploading());
        });
    });

    it('ignores a second start while busy', () => {
        expect(reduce(uploading(30), { type: 'start' })).toEqual(uploading(30));
        const processing = { status: 'processing', percent: 100, error: null };
        expect(reduce(processing, { type: 'start' })).toBe(processing);
    });

    it('turns bytes sent into a rounded percent', () => {
        expect(reduce(uploading(), { type: 'progress', loaded: 1, total: 3 })).toEqual(uploading(33));
        expect(reduce(uploading(), { type: 'progress', loaded: 2, total: 3 })).toEqual(uploading(67));
    });

    it('never shows 100 while bytes are still in flight', () => {
        expect(reduce(uploading(), { type: 'progress', loaded: 999, total: 1000 })).toEqual(uploading(99));
    });

    it('moves to processing once every byte is sent', () => {
        expect(reduce(uploading(50), { type: 'progress', loaded: 10, total: 10 })).toEqual({ status: 'processing', percent: 100, error: null });
    });

    it('ignores progress without a size or outside an upload', () => {
        expect(reduce(uploading(20), { type: 'progress', loaded: 5, total: 0 })).toEqual(uploading(20));
        const done = { status: 'done', percent: 100, error: null };
        expect(reduce(done, { type: 'progress', loaded: 1, total: 2 })).toBe(done);
        expect(reduce(idle(), { type: 'progress', loaded: 1, total: 2 })).toEqual(idle());
    });

    it('finishes from uploading or processing only', () => {
        expect(reduce(uploading(40), { type: 'done' })).toEqual({ status: 'done', percent: 100, error: null });
        expect(reduce({ status: 'processing', percent: 100, error: null }, { type: 'done' }).status).toBe('done');
        expect(reduce(idle(), { type: 'done' })).toEqual(idle());
    });

    it('records a failure with its message', () => {
        expect(reduce(uploading(40), { type: 'fail', error: 'Too big' })).toEqual({ status: 'failed', percent: 0, error: 'Too big' });
        expect(reduce(idle(), { type: 'fail', error: 'x' })).toEqual(idle());
    });

    it('cancels an upload in progress only', () => {
        expect(reduce(uploading(40), { type: 'cancel' })).toEqual({ status: 'cancelled', percent: 0, error: null });
        expect(reduce({ status: 'processing', percent: 100, error: null }, { type: 'cancel' }).status).toBe('cancelled');
        expect(reduce({ status: 'done', percent: 100, error: null }, { type: 'cancel' }).status).toBe('done');
    });

    it('resets to idle and ignores unknown events', () => {
        expect(reduce({ status: 'failed', percent: 0, error: 'x' }, { type: 'reset' })).toEqual(idle());
        const state = uploading(10);
        expect(reduce(state, { type: 'nope' })).toBe(state);
        expect(reduce(state, { type: 'toString' })).toBe(state);
    });

    it('knows when it is busy', () => {
        expect(isBusy(uploading())).toBe(true);
        expect(isBusy({ status: 'processing' })).toBe(true);
        ['idle', 'done', 'failed', 'cancelled'].forEach((status) => expect(isBusy({ status })).toBe(false));
    });
});
