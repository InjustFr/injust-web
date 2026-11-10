import { describe, expect, it } from 'vitest';
import { CENTER, keyFocus, objectPosition, pointerFocus, snap } from './focusPoint.js';

describe('snap', () => {
    it('rounds to the nearest step of 5', () => {
        expect(snap(37)).toBe(35);
        expect(snap(37.5)).toBe(40);
        expect(snap(42.4)).toBe(40);
        expect(snap(50)).toBe(50);
    });

    it('clamps to 0..100', () => {
        expect(snap(-12)).toBe(0);
        expect(snap(0)).toBe(0);
        expect(snap(100)).toBe(100);
        expect(snap(140)).toBe(100);
    });
});

describe('pointerFocus', () => {
    const rect = { left: 100, top: 50, width: 400, height: 200 };

    it('turns a pointer position into snapped percents of the image', () => {
        expect(pointerFocus(240, 170, rect)).toEqual({ x: 35, y: 60 });
        expect(pointerFocus(100, 50, rect)).toEqual({ x: 0, y: 0 });
        expect(pointerFocus(500, 250, rect)).toEqual({ x: 100, y: 100 });
    });

    it('clamps a pointer dragged outside the image', () => {
        expect(pointerFocus(20, 400, rect)).toEqual({ x: 0, y: 100 });
    });
});

describe('keyFocus', () => {
    const focus = { x: 50, y: 50 };

    it('moves by 5 with the arrow keys', () => {
        expect(keyFocus(focus, 'ArrowLeft', false)).toEqual({ x: 45, y: 50 });
        expect(keyFocus(focus, 'ArrowRight', false)).toEqual({ x: 55, y: 50 });
        expect(keyFocus(focus, 'ArrowUp', false)).toEqual({ x: 50, y: 45 });
        expect(keyFocus(focus, 'ArrowDown', false)).toEqual({ x: 50, y: 55 });
    });

    it('moves by 25 with Shift', () => {
        expect(keyFocus(focus, 'ArrowLeft', true)).toEqual({ x: 25, y: 50 });
        expect(keyFocus(focus, 'ArrowDown', true)).toEqual({ x: 50, y: 75 });
    });

    it('stays inside 0..100', () => {
        expect(keyFocus({ x: 90, y: 10 }, 'ArrowRight', true)).toEqual({ x: 100, y: 10 });
        expect(keyFocus({ x: 90, y: 10 }, 'ArrowUp', true)).toEqual({ x: 90, y: 0 });
    });

    it('jumps to the edges with Home, End, Page Up and Page Down', () => {
        const point = { x: 35, y: 60 };
        expect(keyFocus(point, 'Home', false)).toEqual({ x: 0, y: 60 });
        expect(keyFocus(point, 'End', false)).toEqual({ x: 100, y: 60 });
        expect(keyFocus(point, 'PageUp', false)).toEqual({ x: 35, y: 0 });
        expect(keyFocus(point, 'PageDown', true)).toEqual({ x: 35, y: 100 });
    });

    it('ignores other keys', () => {
        expect(keyFocus(focus, 'Enter', false)).toBeNull();
        expect(keyFocus(focus, 'toString', false)).toBeNull();
    });
});

describe('objectPosition', () => {
    it('writes the focus as a CSS position', () => {
        expect(objectPosition({ x: 35, y: 60 })).toBe('35% 60%');
        expect(objectPosition(CENTER)).toBe('50% 50%');
    });
});
