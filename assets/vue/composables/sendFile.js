import { t } from '../i18n/index.js';
import { ApiError, apiFailure } from './useApi.js';

export class UploadCancelled extends Error {}

export function sendFile(url, file, onProgress) {
    const xhr = new XMLHttpRequest();
    const promise = new Promise((resolve, reject) => {
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) onProgress(event.loaded, event.total);
        };
        xhr.onload = () => {
            let payload = null;
            try {
                payload = JSON.parse(xhr.responseText);
            } catch {
                payload = null;
            }
            if (xhr.status >= 200 && xhr.status < 300) return resolve(payload);
            reject(apiFailure(xhr.status, payload));
        };
        xhr.onerror = () => reject(new ApiError(t('common.error'), 0));
        xhr.onabort = () => reject(new UploadCancelled());
        const body = new FormData();
        body.append('file', file);
        xhr.send(body);
    });
    return { promise, abort: () => xhr.abort() };
}
