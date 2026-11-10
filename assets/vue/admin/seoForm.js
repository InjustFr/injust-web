import { pair, trimmed } from './forms.js';

export const PAGE_KEYS = ['lessons', 'production', 'contact', 'links'];

const LIMITS = { title: { warn: 60, max: 70 }, description: { warn: 155, max: 170 } };

export function counter(text, field) {
    const { warn, max } = LIMITS[field];
    const length = [...text].length;
    return { length, max, state: length > max ? 'over' : length > warn ? 'long' : 'ok' };
}

export function seoForm(page) {
    return { title: pair(page.title), description: pair(page.description) };
}

export function seoPayload(form) {
    return { title: trimmed(form.title), description: trimmed(form.description) };
}

const clip = (text, length) => [...text].length > length ? `${[...text].slice(0, length).join('')}…` : text;

export function googlePreview({ title, description }) {
    return { title: clip(title, LIMITS.title.warn), description: clip(description, LIMITS.description.warn) };
}
