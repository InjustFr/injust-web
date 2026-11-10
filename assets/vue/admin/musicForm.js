export const RELEASE_TYPES = ['single', 'ep', 'album'];
export const PLATFORMS = ['spotify', 'apple-music', 'deezer', 'youtube-music', 'youtube', 'bandcamp', 'tidal', 'amazon-music', 'soundcloud', 'other'];
export const NETWORKS = ['instagram', 'tiktok', 'youtube', 'spotify', 'facebook', 'bandcamp', 'soundcloud', 'linktree', 'x', 'threads', 'other'];

const DURATION = /^(\d{1,3}):([0-5]\d)$/;
const TRACK_LINE = /^(?:\d+\s*[.)-]\s+)?(.*?)(?:\s+\(?(\d{1,3}:[0-5]\d)\)?)?$/;

export function parseDuration(value) {
    const match = DURATION.exec(String(value).trim());
    if (!match) return null;
    return Number(match[1]) * 60 + Number(match[2]) || null;
}

export function formatDuration(seconds) {
    if (!seconds) return '';
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

export function parseTracks(text) {
    return text
        .split('\n')
        .map((line) => TRACK_LINE.exec(line.trim()))
        .map((match) => ({ title: match[1].trim(), duration: match[2] ?? '' }))
        .filter((track) => track.title !== '');
}

const orNull = (value) => value.trim() === '' ? null : value.trim();

const NEW_RELEASE = {
    title: '', slug: '', type: 'single', releaseDate: '', description: { fr: '', en: '' },
    tracks: [], links: [], smartLinkUrl: null, spotifyEmbed: null,
};

export function releaseForm(release) {
    const source = release ?? NEW_RELEASE;
    return {
        title: source.title,
        slug: source.slug,
        type: source.type,
        releaseDate: source.releaseDate,
        description: { fr: source.description.fr, en: source.description.en },
        tracks: source.tracks.map((track) => ({ title: track.title, duration: formatDuration(track.durationSeconds) })),
        links: source.links.map((link) => ({ platform: link.platform, url: link.url })),
        smartLinkUrl: source.smartLinkUrl ?? '',
        spotifyUrl: source.spotifyEmbed?.url ?? '',
    };
}

export function releasePayload(form) {
    return {
        title: form.title.trim(),
        slug: orNull(form.slug),
        type: form.type,
        releaseDate: form.releaseDate,
        description: { ...form.description },
        tracks: form.tracks
            .filter((track) => track.title.trim() !== '')
            .map((track) => ({ title: track.title.trim(), durationSeconds: parseDuration(track.duration) })),
        links: form.links
            .filter((link) => link.url.trim() !== '')
            .map((link) => ({ platform: link.platform, url: link.url.trim() })),
        smartLinkUrl: orNull(form.smartLinkUrl),
        spotifyUrl: orNull(form.spotifyUrl),
    };
}

export function hooksText(hooks) {
    return hooks.join('\n');
}

export function hooksPayload(text) {
    return text.split('\n').map((line) => line.trim()).filter((line) => line !== '');
}

export function textForm(text) {
    return {
        tagline: { ...text.tagline },
        manifesto: { ...text.manifesto },
        bio: { ...text.bio },
        hooks: hooksText(text.hooks),
    };
}

export function textPayload(form) {
    return { tagline: { ...form.tagline }, manifesto: { ...form.manifesto }, bio: { ...form.bio }, hooks: hooksPayload(form.hooks) };
}

export function moveId(ids, id, delta) {
    const from = ids.indexOf(id);
    const to = from + delta;
    if (from === -1 || to < 0 || to >= ids.length) return [...ids];
    const next = [...ids];
    next.splice(from, 1);
    next.splice(to, 0, id);
    return next;
}
