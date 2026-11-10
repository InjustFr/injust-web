import { getCurrentScope, onScopeDispose, ref } from 'vue';
import { t } from '../i18n/index.js';

export class ApiError extends Error {
    constructor(message, status, violations = []) {
        super(message);
        this.status = status;
        this.violations = violations;
    }

    get fieldErrors() {
        return Object.fromEntries(this.violations.map((v) => [v.propertyPath, v.title ?? v.message]));
    }
}

const REFRESH_HEADER = 'X-Refresh';
const REFRESHED_HEADER = 'X-Refreshed';

const cache = new Map();
const targets = new Map();
const fetching = new Map();
const pending = ref(0);
let generation = 0;
let preloaded = null;

function takePreloaded(url) {
    if (preloaded === null) {
        const element = document.getElementById('app-preload');
        preloaded = new Map(element ? Object.entries(JSON.parse(element.textContent)) : []);
        element?.remove();
    }
    if (!preloaded.has(url)) return { found: false };
    const data = preloaded.get(url);
    preloaded.delete(url);
    return { found: true, data };
}

function publish(url, data) {
    cache.set(url, data);
    targets.get(url)?.forEach((target) => { target.value = data; });
}

function follow(url, target) {
    if (!targets.has(url)) targets.set(url, new Set());
    targets.get(url).add(target);
    if (getCurrentScope()) {
        onScopeDispose(() => {
            targets.get(url)?.delete(target);
            if (targets.get(url)?.size === 0) targets.delete(url);
        });
    }
}

async function send(method, url, body, refresh = []) {
    const json = body !== undefined && !(body instanceof FormData);
    pending.value += 1;
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(json ? { 'Content-Type': 'application/json' } : {}),
                ...(refresh.length > 0 ? { [REFRESH_HEADER]: JSON.stringify(refresh) } : {}),
            },
            body: json ? JSON.stringify(body) : body,
        });

        if (response.status === 401) {
            window.location.assign('/login');
        }

        const payload = response.status === 204 ? null : await response.json().catch(() => null);

        if (!response.ok) {
            throw new ApiError(payload?.detail ?? payload?.title ?? t('common.error'), response.status, payload?.violations ?? []);
        }

        if (method === 'GET') return payload;

        return response.headers.has(REFRESHED_HEADER) ? payload : { data: payload, refreshed: {} };
    } finally {
        pending.value -= 1;
    }
}

function fresh(url) {
    if (!fetching.has(url)) {
        const startedAt = generation;
        const request = send('GET', url)
            .then((data) => {
                if (startedAt === generation) publish(url, data);
                return cache.get(url) ?? data;
            })
            .finally(() => fetching.delete(url));
        fetching.set(url, request);
    }
    return fetching.get(url);
}

async function load(url, target) {
    follow(url, target);
    const preload = takePreloaded(url);
    if (preload.found) {
        publish(url, preload.data);
        return preload.data;
    }
    if (cache.has(url)) {
        target.value = cache.get(url);
        fresh(url).catch(() => {});
        return target.value;
    }
    return fresh(url);
}

async function change(method, url, body) {
    const { data, refreshed = {} } = await send(method, url, body, [...targets.keys()]);
    generation += 1;
    Object.entries(refreshed).forEach(([refreshedUrl, value]) => publish(refreshedUrl, value));
    return data;
}

async function refreshAll() {
    await Promise.allSettled([...targets.keys()].map((url) => fresh(url)));
}

export function useApi() {
    return {
        load,
        refreshAll,
        pending,
        get: (url) => send('GET', url),
        post: (url, body = {}) => change('POST', url, body),
        put: (url, body = {}) => change('PUT', url, body),
        patch: (url, body = {}) => change('PATCH', url, body),
        del: (url) => change('DELETE', url),
    };
}
