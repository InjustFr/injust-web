import { describe, expect, it } from 'vitest';
import { filterBeats, formatBpm, formatDuration, idle, nextSort, player, progress, sortBeats } from './beatTable.js';

const riot = { id: 'R', title: 'Riot Gear', bpm: 140, key: 'a-minor', tags: ['punk', 'dark'] };
const ash = { id: 'A', title: 'ash', bpm: 92, key: 'c-major', tags: ['lofi'] };
const burn = { id: 'B', title: 'Burn', bpm: 170, key: 'a-minor', tags: ['punk'] };
const rows = [riot, ash, burn];
const none = { tags: [], key: '', min: null, max: null };
const ids = (list) => list.map((row) => row.id);

describe('filterBeats', () => {
    it('keeps every beat without a filter', () => {
        expect(ids(filterBeats(rows, none))).toEqual(['R', 'A', 'B']);
    });

    it('keeps the beats that carry every chosen tag', () => {
        expect(ids(filterBeats(rows, { ...none, tags: ['punk'] }))).toEqual(['R', 'B']);
        expect(ids(filterBeats(rows, { ...none, tags: ['punk', 'dark'] }))).toEqual(['R']);
    });

    it('keeps the beats in the chosen key', () => {
        expect(ids(filterBeats(rows, { ...none, key: 'a-minor' }))).toEqual(['R', 'B']);
    });

    it('keeps the beats inside an inclusive BPM range', () => {
        expect(ids(filterBeats(rows, { ...none, min: 140 }))).toEqual(['R', 'B']);
        expect(ids(filterBeats(rows, { ...none, max: 140 }))).toEqual(['R', 'A']);
        expect(ids(filterBeats(rows, { ...none, min: 93, max: 169 }))).toEqual(['R']);
    });

    it('ignores an empty or invalid bound', () => {
        expect(ids(filterBeats(rows, { ...none, min: '', max: 'fast' }))).toEqual(['R', 'A', 'B']);
        expect(ids(filterBeats(rows, { ...none, min: '100' }))).toEqual(['R', 'B']);
    });

    it('combines the filters', () => {
        expect(ids(filterBeats(rows, { tags: ['punk'], key: 'a-minor', min: 150, max: null }))).toEqual(['B']);
    });
});

describe('sortBeats', () => {
    it('keeps the catalogue order by default and does not touch the list', () => {
        const sorted = sortBeats(rows, { by: 'position', direction: 'ascending' });

        expect(ids(sorted)).toEqual(['R', 'A', 'B']);
        expect(sorted).not.toBe(rows);
    });

    it('sorts by BPM both ways', () => {
        expect(ids(sortBeats(rows, { by: 'bpm', direction: 'ascending' }))).toEqual(['A', 'R', 'B']);
        expect(ids(sortBeats(rows, { by: 'bpm', direction: 'descending' }))).toEqual(['B', 'R', 'A']);
        expect(ids(rows)).toEqual(['R', 'A', 'B']);
    });

    it('sorts by title without caring about case', () => {
        expect(ids(sortBeats(rows, { by: 'title', direction: 'ascending' }))).toEqual(['A', 'B', 'R']);
        expect(ids(sortBeats(rows, { by: 'title', direction: 'descending' }))).toEqual(['R', 'B', 'A']);
    });

    it('keeps the catalogue order between equal values', () => {
        const twin = { ...burn, id: 'T', bpm: 140 };

        expect(ids(sortBeats([twin, riot], { by: 'bpm', direction: 'descending' }))).toEqual(['T', 'R']);
    });
});

describe('nextSort', () => {
    it('sorts a new column ascending, then flips it, then goes back to the catalogue order', () => {
        const first = nextSort({ by: 'position', direction: 'ascending' }, 'bpm');
        const second = nextSort(first, 'bpm');

        expect(first).toEqual({ by: 'bpm', direction: 'ascending' });
        expect(second).toEqual({ by: 'bpm', direction: 'descending' });
        expect(nextSort(second, 'bpm')).toEqual({ by: 'position', direction: 'ascending' });
        expect(nextSort(second, 'title')).toEqual({ by: 'title', direction: 'ascending' });
    });
});

describe('formatDuration', () => {
    it('shows minutes and padded seconds', () => {
        expect(formatDuration(0)).toBe('0:00');
        expect(formatDuration(9.9)).toBe('0:09');
        expect(formatDuration(65.4)).toBe('1:05');
        expect(formatDuration(600)).toBe('10:00');
    });

    it('shows zero for an unknown duration', () => {
        expect(formatDuration(Number.NaN)).toBe('0:00');
        expect(formatDuration(Number.POSITIVE_INFINITY)).toBe('0:00');
        expect(formatDuration(-3)).toBe('0:00');
    });
});

describe('formatBpm', () => {
    it('rounds the tempo and names the unit', () => {
        expect(formatBpm(140)).toBe('140 BPM');
        expect(formatBpm(92.6)).toBe('93 BPM');
    });
});

describe('progress', () => {
    it('is the played share of the preview, between 0 and 1', () => {
        expect(progress(30, 120)).toBe(0.25);
        expect(progress(150, 120)).toBe(1);
        expect(progress(-1, 120)).toBe(0);
    });

    it('is zero before the duration is known', () => {
        expect(progress(3, 0)).toBe(0);
        expect(progress(3, Number.NaN)).toBe(0);
        expect(progress(3, Number.POSITIVE_INFINITY)).toBe(0);
    });
});

describe('player', () => {
    const queue = ['R', 'A', 'B'];

    it('starts idle', () => {
        expect(idle).toEqual({ current: null, playing: false });
    });

    it('plays a beat when its button is pressed and pauses it on the second press', () => {
        const playing = player(idle, { type: 'toggle', id: 'A' });

        expect(playing).toEqual({ current: 'A', playing: true });
        expect(player(playing, { type: 'toggle', id: 'A' })).toEqual({ current: 'A', playing: false });
        expect(player({ current: 'A', playing: false }, { type: 'toggle', id: 'A' })).toEqual({ current: 'A', playing: true });
    });

    it('plays only one preview at a time', () => {
        expect(player({ current: 'A', playing: true }, { type: 'toggle', id: 'B' })).toEqual({ current: 'B', playing: true });
        expect(player({ current: 'A', playing: false }, { type: 'toggle', id: 'B' })).toEqual({ current: 'B', playing: true });
    });

    it('follows the audio element when it pauses or plays by itself', () => {
        expect(player({ current: 'A', playing: true }, { type: 'pause' })).toEqual({ current: 'A', playing: false });
        expect(player({ current: 'A', playing: false }, { type: 'play' })).toEqual({ current: 'A', playing: true });
        expect(player(idle, { type: 'play' })).toBe(idle);
    });

    it('moves to the next listed beat when a preview ends', () => {
        expect(player({ current: 'R', playing: true }, { type: 'ended', queue })).toEqual({ current: 'A', playing: true });
        expect(player({ current: 'A', playing: false }, { type: 'next', queue })).toEqual({ current: 'B', playing: true });
    });

    it('stops after the last beat or when the beat left the list', () => {
        expect(player({ current: 'B', playing: true }, { type: 'ended', queue })).toEqual(idle);
        expect(player({ current: 'X', playing: true }, { type: 'next', queue })).toEqual(idle);
        expect(player(idle, { type: 'next', queue })).toEqual(idle);
    });

    it('ignores an unknown event', () => {
        const state = { current: 'A', playing: true };

        expect(player(state, { type: 'seek' })).toBe(state);
    });
});
