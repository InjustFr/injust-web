import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const before = (a, b) => a.line < b.line || (a.line === b.line && a.column <= b.column);
const contains = (outer, inner) => before(outer.start, inner.start) && before(inner.end, outer.end);
const size = ({ start, end }) => (end.line - start.line) * 1000 + end.column - start.column;

export function crapOfFile(file) {
    const fns = Object.values(file.fnMap).map((fn) => ({ fn, complexity: 1, statements: 0, covered: 0 }));
    const owner = (loc) => fns.filter(({ fn }) => contains(fn.loc, loc)).sort((a, b) => size(a.fn.loc) - size(b.fn.loc))[0];

    for (const branch of Object.values(file.branchMap)) {
        const target = owner(branch.loc);
        if (target) target.complexity += Math.max(branch.locations.length - 1, 0);
    }
    for (const [id, loc] of Object.entries(file.statementMap)) {
        const target = owner(loc);
        if (!target) continue;
        target.statements += 1;
        if (file.s[id] > 0) target.covered += 1;
    }

    return fns.map(({ fn, complexity, statements, covered }) => {
        const coverage = statements === 0 ? 1 : covered / statements;
        return { name: fn.name, line: fn.loc.start.line, complexity, coverage, crap: complexity ** 2 * (1 - coverage) ** 3 + complexity };
    });
}

const missing = (file) => ({ file, name: '(missing from coverage)', line: 0, crap: Infinity });

export function crapViolations(coverage, max, files) {
    return files.flatMap((file) => {
        const key = Object.keys(coverage).find((path) => path === file || path.endsWith(`/${file}`));
        if (!key) return [missing(file)];
        return crapOfFile(coverage[key])
            .filter(({ crap }) => crap > max)
            .map(({ name, line, crap }) => ({ file, name, line, crap }));
    });
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const [report, max, ...files] = process.argv.slice(2);
    if (!report || !max) {
        console.error('Usage: bin/crap-js.mjs <coverage-final.json> <max> [changed modules...]');
        process.exit(2);
    }
    const violations = crapViolations(JSON.parse(readFileSync(report, 'utf8')), Number(max), files);
    for (const { file, name, line, crap } of violations) console.error(`CRAP ${crap} > ${max}: ${file}:${line} ${name}`);
    process.exit(violations.length === 0 ? 0 : 1);
}
