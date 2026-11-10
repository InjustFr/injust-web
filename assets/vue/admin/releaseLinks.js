export const PUBLIC_ORDER = ['spotify', 'apple-music', 'youtube-music', 'deezer', 'tidal', 'amazon-music', 'youtube', 'bandcamp', 'soundcloud', 'other'];

const ENV_KEYS = { spotify: ['SPOTIFY_CLIENT_ID', 'SPOTIFY_CLIENT_SECRET'], tidal: ['TIDAL_CLIENT_ID', 'TIDAL_CLIENT_SECRET'] };
const KINDS = { found: 'success', notFound: 'info', notConfigured: 'info', failed: 'error' };

export const normalizeUpc = (value) => String(value).replace(/[\s-]/g, '');

export function isValidUpc(value) {
    if (!/^(\d{12}|\d{13})$/.test(value)) return false;
    const digits = [...value].map(Number);
    const check = digits.pop();
    const sum = digits.reverse().reduce((total, digit, index) => total + digit * (index % 2 === 0 ? 3 : 1), 0);
    return (10 - (sum % 10)) % 10 === check;
}

export function linkRows(release) {
    return PUBLIC_ORDER.map((platform) => {
        const links = release.links.filter((link) => link.platform === platform);
        const link = links.find((item) => item.source === 'manual') ?? links[0];
        return {
            platform,
            url: link?.url ?? null,
            source: link?.source ?? null,
            clicks: release.clicks[platform] ?? 0,
            hidden: release.hiddenPlatforms.includes(platform),
        };
    });
}

export function hiddenAfter(hidden, platform, hide) {
    const rest = hidden.filter((item) => item !== platform);
    return hide ? [...rest, platform] : rest;
}

export const reportKind = (status) => KINDS[status] ?? 'info';
export const missingFinders = (finders) => finders.filter((finder) => !finder.configured);
export const finderEnvKeys = (platform) => ENV_KEYS[platform] ?? [];
