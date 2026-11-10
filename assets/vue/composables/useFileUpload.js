import { ref } from 'vue';
import { idle, isBusy, reduce } from '../shared/uploadState.js';
import { UploadCancelled, sendFile } from './sendFile.js';

export function useFileUpload(url, { onDone }) {
    const state = ref(idle());
    let current = null;
    const dispatch = (event) => { state.value = reduce(state.value, event); };

    async function start(file) {
        if (isBusy(state.value)) return false;
        dispatch({ type: 'start' });
        current = sendFile(url, file, (loaded, total) => dispatch({ type: 'progress', loaded, total }));
        try {
            await current.promise;
        } catch (error) {
            dispatch(error instanceof UploadCancelled ? { type: 'cancel' } : { type: 'fail', error: error.message });
            return false;
        }
        dispatch({ type: 'done' });
        await onDone();
        return true;
    }

    const cancel = () => current?.abort();
    const reset = () => dispatch({ type: 'reset' });

    return { state, start, cancel, reset };
}
