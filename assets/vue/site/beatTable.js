export const idle = Object.freeze({ current: null, playing: false });

const catalogue = Object.freeze({ by: 'position', direction: 'ascending' });

function bound(value) {
    if (value === '' || value === null) {
        return null;
    }
    const number = Number(value);

    return Number.isFinite(number) ? number : null;
}

export function filterBeats(rows, filter) {
    const min = bound(filter.min);
    const max = bound(filter.max);

    return rows.filter((row) => filter.tags.every((tag) => row.tags.includes(tag))
        && (filter.key === '' || row.key === filter.key)
        && (min === null || row.bpm >= min)
        && (max === null || row.bpm <= max));
}

const compare = {
    bpm: (a, b) => a.bpm - b.bpm,
    title: (a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }),
};

export function sortBeats(rows, sort) {
    const order = compare[sort.by];
    if (!order) {
        return [...rows];
    }
    const sign = sort.direction === 'descending' ? -1 : 1;

    return [...rows].sort((a, b) => sign * order(a, b));
}

export function nextSort(sort, by) {
    if (sort.by !== by) {
        return { by, direction: 'ascending' };
    }

    return sort.direction === 'ascending' ? { by, direction: 'descending' } : catalogue;
}

export function formatDuration(seconds) {
    const whole = Number.isFinite(seconds) && seconds > 0 ? Math.floor(seconds) : 0;

    return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
}

export function formatBpm(bpm) {
    return `${Math.round(bpm)} BPM`;
}

export function progress(time, duration) {
    if (!Number.isFinite(duration) || duration <= 0) {
        return 0;
    }

    return Math.min(1, Math.max(0, time / duration));
}

function next(state, queue) {
    const index = queue.indexOf(state.current);
    if (index === -1 || index === queue.length - 1) {
        return idle;
    }

    return { current: queue[index + 1], playing: true };
}

export function player(state, event) {
    switch (event.type) {
        case 'toggle':
            return { current: event.id, playing: state.current !== event.id || !state.playing };
        case 'pause':
            return { ...state, playing: false };
        case 'play':
            return state.current === null ? state : { ...state, playing: true };
        case 'ended':
        case 'next':
            return next(state, event.queue);
        default:
            return state;
    }
}
