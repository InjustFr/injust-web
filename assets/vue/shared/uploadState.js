export const idle = () => ({ status: 'idle', percent: 0, error: null });

export const isBusy = (state) => state.status === 'uploading' || state.status === 'processing';

function progress(state, { loaded, total }) {
    if (state.status !== 'uploading' || total <= 0) return state;
    return loaded >= total
        ? { status: 'processing', percent: 100, error: null }
        : { status: 'uploading', percent: Math.min(99, Math.round((loaded / total) * 100)), error: null };
}

const whileBusy = (next) => (state, event) => (isBusy(state) ? next(event) : state);

const TRANSITIONS = {
    start: (state) => (isBusy(state) ? state : { status: 'uploading', percent: 0, error: null }),
    progress,
    done: whileBusy(() => ({ status: 'done', percent: 100, error: null })),
    fail: whileBusy((event) => ({ status: 'failed', percent: 0, error: event.error })),
    cancel: whileBusy(() => ({ status: 'cancelled', percent: 0, error: null })),
    reset: idle,
};

export const reduce = (state, event) => (Object.hasOwn(TRANSITIONS, event.type) ? TRANSITIONS[event.type](state, event) : state);
