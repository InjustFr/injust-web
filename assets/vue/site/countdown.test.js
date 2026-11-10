import { describe, expect, it } from 'vitest';
import { remaining } from './countdown.js';

const target = Date.parse('2026-12-04T00:00:00+01:00');
const second = 1000;
const minute = 60 * second;
const hour = 60 * minute;
const day = 24 * hour;

describe('remaining', () => {
    it('splits the time left into days, hours, minutes and seconds', () => {
        expect(remaining(target - (23 * day + 14 * hour + 5 * minute + 9 * second), target)).toEqual({ days: 23, hours: 14, minutes: 5, seconds: 9, done: false });
    });

    it('rounds a partial second down', () => {
        expect(remaining(target - (hour + 999), target)).toEqual({ days: 0, hours: 1, minutes: 0, seconds: 0, done: false });
    });

    it('is not done one millisecond before the target', () => {
        expect(remaining(target - 1, target)).toEqual({ days: 0, hours: 0, minutes: 0, seconds: 0, done: false });
    });

    it('is done at the target and never goes negative after it', () => {
        expect(remaining(target, target)).toEqual({ days: 0, hours: 0, minutes: 0, seconds: 0, done: true });
        expect(remaining(target + 3 * day, target)).toEqual({ days: 0, hours: 0, minutes: 0, seconds: 0, done: true });
    });

    it('reads ISO dates and Date objects', () => {
        expect(remaining(new Date(target - day), '2026-12-04T00:00:00+01:00')).toEqual({ days: 1, hours: 0, minutes: 0, seconds: 0, done: false });
    });
});
