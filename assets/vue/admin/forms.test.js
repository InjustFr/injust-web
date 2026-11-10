import { describe, expect, it } from 'vitest';
import { centsToEuros, eurosToCents, pair, trimmed } from './forms.js';

describe('euros', () => {
    it('parses whole, comma and dot amounts into cents', () => {
        expect(eurosToCents('45')).toBe(4500);
        expect(eurosToCents('45,50')).toBe(4550);
        expect(eurosToCents('45.5')).toBe(4550);
        expect(eurosToCents(' 0,05 ')).toBe(5);
        expect(eurosToCents('0')).toBe(0);
        expect(eurosToCents('19.99')).toBe(1999);
        expect(eurosToCents('1,005')).toBeNull();
    });

    it('refuses anything else', () => {
        expect(eurosToCents('')).toBeNull();
        expect(eurosToCents('abc')).toBeNull();
        expect(eurosToCents('-5')).toBeNull();
        expect(eurosToCents('4 5')).toBeNull();
        expect(eurosToCents('x45')).toBeNull();
        expect(eurosToCents('45x')).toBeNull();
        expect(eurosToCents('4,5,5')).toBeNull();
    });

    it('formats cents as a plain euro string', () => {
        expect(centsToEuros(4500)).toBe('45');
        expect(centsToEuros(4550)).toBe('45.50');
        expect(centsToEuros(5)).toBe('0.05');
        expect(centsToEuros(0)).toBe('0');
        expect(centsToEuros(null)).toBe('');
    });
});


describe('localized pairs', () => {
    it('copies only the two locales', () => {
        expect(pair({ fr: ' a ', en: 'b', extra: 1 })).toEqual({ fr: ' a ', en: 'b' });
    });

    it('trims both locales', () => {
        expect(trimmed({ fr: ' a ', en: ' b\n' })).toEqual({ fr: 'a', en: 'b' });
    });
});
