import { readdirSync, readFileSync } from 'node:fs';
import { expect, test } from 'vitest';

const directory = new URL('./', import.meta.url);

function keys(value, prefix = '') {
    if (typeof value !== 'object' || value === null) return [prefix];
    return Object.entries(value).flatMap(([key, child]) => keys(child, prefix ? `${prefix}.${key}` : key));
}

function messages(language) {
    return Object.fromEntries(
        readdirSync(new URL(`${language}/`, directory))
            .filter((file) => file.endsWith('.json'))
            .map((file) => [file.replace('.json', ''), JSON.parse(readFileSync(new URL(`${language}/${file}`, directory), 'utf8'))]),
    );
}

test('every message exists in English and French', () => {
    expect(keys(messages('fr')).sort()).toEqual(keys(messages('en')).sort());
});
