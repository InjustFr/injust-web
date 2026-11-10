import { pair, trimmed } from './forms.js';

export function linkForm(link) {
    return {
        label: pair(link?.label ?? { fr: '', en: '' }),
        url: link?.url ?? '',
        icon: link?.icon ?? '',
        highlighted: link?.highlighted ?? false,
        visible: link?.visible ?? true,
    };
}

export function linkPayload(form) {
    return {
        label: trimmed(form.label),
        url: form.url.trim(),
        icon: form.icon === '' ? null : form.icon,
        highlighted: form.highlighted,
        visible: form.visible,
    };
}

export function hostOf(url) {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}
