import { describe, expect, it } from 'vitest';
import { SLOTS, needsFraming, ratioMismatch } from './imageFraming.js';

describe('ratioMismatch', () => {
    it('is false within 2% of the slot ratio', () => {
        expect(ratioMismatch(1, 1)).toBe(false);
        expect(ratioMismatch(1.019, 1)).toBe(false);
        expect(ratioMismatch(0.981, 1)).toBe(false);
        expect(ratioMismatch(51, 50)).toBe(false);
        expect(ratioMismatch(0.81, 0.8)).toBe(false);
    });

    it('is true beyond 2%', () => {
        expect(ratioMismatch(1.03, 1)).toBe(true);
        expect(ratioMismatch(0.97, 1)).toBe(true);
        expect(ratioMismatch(1.5, 1)).toBe(true);
    });

    it('measures the gap relative to the slot ratio', () => {
        expect(ratioMismatch(0.83, 0.8)).toBe(true);
        expect(ratioMismatch(2.03, 2)).toBe(false);
    });
});

describe('needsFraming', () => {
    it('always asks for the hero', () => {
        expect(needsFraming(SLOTS.hero, 16 / 9)).toBe(true);
        expect(needsFraming(SLOTS.hero, null)).toBe(true);
    });

    it('asks for a cover only when it is not square', () => {
        expect(needsFraming(SLOTS.cover, 1)).toBe(false);
        expect(needsFraming(SLOTS.cover, 16 / 9)).toBe(true);
    });

    it('asks for a portrait only when it is not 4:5', () => {
        expect(needsFraming(SLOTS.portrait, 0.8)).toBe(false);
        expect(needsFraming(SLOTS.portrait, 1)).toBe(true);
    });

    it('asks when any frame of the slot does not fit', () => {
        expect(needsFraming({ always: false, frames: [{ ratio: 1 }, { ratio: 2 }] }, 1)).toBe(true);
    });

    it('does not ask when the image size is unknown', () => {
        expect(needsFraming(SLOTS.cover, null)).toBe(false);
    });
});

describe('slots', () => {
    it('shows a phone and a desktop frame for the hero', () => {
        expect(SLOTS.hero.frames).toEqual([{ key: 'phone', ratio: 9 / 19.5 }, { key: 'desktop', ratio: 16 / 9 }]);
    });

    it('frames covers square and portraits at 4:5', () => {
        expect(SLOTS.cover).toEqual({ always: false, frames: [{ key: 'square', ratio: 1 }] });
        expect(SLOTS.portrait).toEqual({ always: false, frames: [{ key: 'portrait', ratio: 4 / 5 }] });
    });
});
