import { centsToEuros, eurosToCents, optionalInt, pair, trimmed } from './forms.js';

const MB = 1024 * 1024;

export const KEYS = ['c', 'c-sharp', 'd', 'd-sharp', 'e', 'f', 'f-sharp', 'g', 'g-sharp', 'a', 'a-sharp', 'b'].flatMap((note) => [`${note}-major`, `${note}-minor`]);
export const DELIVERABLES = ['mp3', 'wav', 'stems'];
export const FILE_SLOTS = ['preview', 'mp3', 'wav', 'stems'];
export const FILE_LIMITS = { preview: 20 * MB, mp3: 50 * MB, wav: 500 * MB, stems: 2048 * MB };
export const FILE_ACCEPT = {
    preview: 'audio/mpeg,.mp3',
    mp3: 'audio/mpeg,.mp3',
    wav: 'audio/wav,audio/x-wav,.wav',
    stems: 'application/zip,.zip',
};
export const CAPS = ['streamCap', 'copyCap', 'videoCap'];

export const keyLabel = (key) => {
    const label = key.replace('-sharp', '#').replace('-', ' ');
    return label[0].toUpperCase() + label.slice(1);
};

export const parseTags = (text) => [...new Set(text.split(',').map((tag) => tag.trim().toLowerCase()).filter((tag) => tag !== ''))];

export const beatForm = (beat) => beat
    ? { title: beat.title, slug: beat.slug, bpm: beat.bpm, key: beat.key, tags: beat.tags.join(', '), visible: beat.visible }
    : { title: '', slug: '', bpm: '', key: 'a-minor', tags: '', visible: false };

export const beatPayload = (form) => ({
    title: form.title.trim(),
    slug: String(form.slug).trim() === '' ? null : form.slug.trim(),
    bpm: optionalInt(form.bpm),
    key: form.key,
    tags: parseTags(form.tags),
    visible: form.visible,
});

export const tierForm = (tier) => tier
    ? {
        name: pair(tier.name),
        summary: pair(tier.summary),
        price: centsToEuros(tier.price.cents),
        deliverables: [...tier.deliverables],
        exclusive: tier.exclusive,
        streamCap: tier.streamCap ?? '',
        copyCap: tier.copyCap ?? '',
        videoCap: tier.videoCap ?? '',
    }
    : { name: { fr: '', en: '' }, summary: { fr: '', en: '' }, price: '', deliverables: ['mp3'], exclusive: false, streamCap: '', copyCap: '', videoCap: '' };

export const tierPayload = (form) => ({
    name: trimmed(form.name),
    summary: trimmed(form.summary),
    price: { cents: eurosToCents(form.price) },
    deliverables: DELIVERABLES.filter((deliverable) => form.deliverables.includes(deliverable)),
    exclusive: form.exclusive,
    streamCap: optionalInt(form.streamCap),
    copyCap: optionalInt(form.copyCap),
    videoCap: optionalInt(form.videoCap),
});

export const capsOf = (tier) => CAPS.filter((cap) => tier[cap] !== null).map((cap) => [cap, tier[cap]]);

export const pageForm = (page) => ({ intro: pair(page.intro), faq: pair(page.faq) });

export const pagePayload = (form) => ({ intro: trimmed(form.intro), faq: trimmed(form.faq) });

export const fileUrl = (beatId, slot) => slot === 'preview' ? `/api/admin/beats/${beatId}/preview` : `/api/admin/beats/${beatId}/files/${slot}`;

export const slotFile = (beat, slot) => slot === 'preview' ? beat.preview : beat.files[slot];

export const fileChips = (beat) => FILE_SLOTS.map((slot) => ({ slot, present: slotFile(beat, slot) !== null }));

export const exceedsLimit = (slot, file) => file.size > FILE_LIMITS[slot];

export function formatSize(bytes, locale = 'en') {
    const units = ['B', 'KB', 'MB', 'GB'];
    const power = Math.min(Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)), units.length - 1);
    return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(bytes / 1024 ** power)} ${units[power]}`;
}
