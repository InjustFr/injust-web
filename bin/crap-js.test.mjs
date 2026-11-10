import { describe, expect, it } from 'vitest';
import { crapOfFile, crapViolations } from './crap-js.mjs';

const span = (startLine, endLine) => ({ start: { line: startLine, column: 0 }, end: { line: endLine, column: 1 } });
const at = (line) => span(line, line);

const fileOf = ({ fns, statements, branches = [] }) => ({
    path: '/app/assets/vue/lib/x.js',
    fnMap: Object.fromEntries(fns.map(([name, loc], i) => [i, { name, loc, decl: loc }])),
    statementMap: Object.fromEntries(statements.map(([loc], i) => [i, loc])),
    s: Object.fromEntries(statements.map(([, hits], i) => [i, hits])),
    branchMap: Object.fromEntries(branches.map(([loc, count], i) => [i, { loc, locations: Array.from({ length: count }, () => loc) }])),
});

describe('crapOfFile', () => {
    it('scores a covered straight-line function as its complexity', () => {
        const [fn] = crapOfFile(fileOf({ fns: [['a', span(1, 3)]], statements: [[at(2), 4]] }));

        expect(fn).toMatchObject({ name: 'a', line: 1, complexity: 1, coverage: 1, crap: 1 });
    });

    it('adds one complexity point per extra branch location', () => {
        const [fn] = crapOfFile(
            fileOf({
                fns: [['a', span(1, 9)]],
                statements: [[at(2), 1]],
                branches: [[at(3), 2], [at(4), 2], [at(5), 3], [at(6), 1]],
            }),
        );

        expect(fn.complexity).toBe(5);
    });

    it('applies c^2 (1 - cov)^3 + c with the share of covered statements', () => {
        const [fn] = crapOfFile(
            fileOf({
                fns: [['a', span(1, 9)]],
                statements: [[at(2), 1], [at(3), 0]],
                branches: [[at(4), 2]],
            }),
        );

        expect(fn.coverage).toBe(0.5);
        expect(fn.crap).toBeCloseTo(2.5);
    });

    it('scores a function without statements as covered', () => {
        const [fn] = crapOfFile(fileOf({ fns: [['a', span(1, 1)]], statements: [] }));

        expect(fn).toMatchObject({ coverage: 1, crap: 1 });
    });

    it('gives nested functions their own branches and statements', () => {
        const [outer, inner] = crapOfFile(
            fileOf({
                fns: [['outer', span(1, 20)], ['inner', span(5, 10)]],
                statements: [[at(2), 1], [at(6), 0]],
                branches: [[at(7), 2], [at(8), 2], [at(12), 2]],
            }),
        );

        expect(outer).toMatchObject({ complexity: 2, coverage: 1 });
        expect(inner).toMatchObject({ complexity: 3, coverage: 0 });
    });
});

describe('crapViolations', () => {
    const coverage = {
        '/app/assets/vue/lib/risky.js': {
            ...fileOf({ fns: [['risky', span(1, 9)]], statements: [[at(2), 0]], branches: [[at(3), 5]] }),
            path: '/app/assets/vue/lib/risky.js',
        },
        '/app/assets/vue/lib/safe.js': {
            ...fileOf({ fns: [['safe', span(1, 3)]], statements: [[at(2), 1]] }),
            path: '/app/assets/vue/lib/safe.js',
        },
        '/app/assets/vue/lib/other-risky.js': {
            ...fileOf({ fns: [['other', span(1, 9)]], statements: [[at(2), 0]], branches: [[at(3), 5]] }),
            path: '/app/assets/vue/lib/other-risky.js',
        },
    };

    it('reports only listed files above the threshold', () => {
        const violations = crapViolations(coverage, 10, ['assets/vue/lib/risky.js', 'assets/vue/lib/safe.js']);

        expect(violations).toEqual([{ file: 'assets/vue/lib/risky.js', name: 'risky', line: 1, crap: 30 }]);
    });

    it('keeps a function exactly at the threshold', () => {
        expect(crapViolations(coverage, 30, ['assets/vue/lib/risky.js'])).toEqual([]);
    });

    it('matches on whole path segments only', () => {
        const violations = crapViolations(coverage, 10, ['vue/lib/risky.js']);

        expect(violations.map((v) => v.file)).toEqual(['vue/lib/risky.js']);
        expect(crapViolations(coverage, 10, ['ky.js'])).toEqual([{ file: 'ky.js', name: '(missing from coverage)', line: 0, crap: Infinity }]);
    });

    it('flags a changed module that the coverage report does not know', () => {
        const violations = crapViolations(coverage, 10, ['assets/vue/lib/untested.js']);

        expect(violations).toEqual([{ file: 'assets/vue/lib/untested.js', name: '(missing from coverage)', line: 0, crap: Infinity }]);
    });

    it('reports nothing when nothing changed', () => {
        expect(crapViolations(coverage, 10, [])).toEqual([]);
    });
});
