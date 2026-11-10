import { reactive } from 'vue';
import { SLOTS, needsFraming } from '../shared/imageFraming.js';
import { objectPosition } from '../shared/focusPoint.js';
import { useApi } from './useApi.js';

const focuses = reactive({});
let listed = false;
const focusUrl = (id) => `/api/admin/images/${id}/focus`;

function remember({ id, x, y }) {
    focuses[id] = { x, y };
    return focuses[id];
}

function list(all) {
    all.filter(({ id }) => !(id in focuses)).forEach(remember);
}

export function imageRatio(url) {
    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve(image.naturalWidth / image.naturalHeight);
        image.onerror = () => resolve(null);
        image.src = url;
    });
}

export function useImageFocus() {
    const api = useApi();
    return {
        position(id) {
            if (!id) return undefined;
            if (!listed) {
                listed = true;
                api.get('/api/admin/images/focus').then(list, () => {});
            }
            return focuses[id] ? objectPosition(focuses[id]) : undefined;
        },
        load: async (id) => remember(await api.get(focusUrl(id))),
        save: async (id, focus) => remember(await api.put(focusUrl(id), focus)),
    };
}

export function useImageFraming(kind, urlOf) {
    const state = reactive({ kind, open: false, imageId: null });
    function adjust(id) {
        state.imageId = id;
        state.open = true;
    }
    return {
        state,
        adjust,
        async uploaded(id) {
            if (id && needsFraming(SLOTS[kind], await imageRatio(urlOf(id)))) adjust(id);
        },
    };
}
