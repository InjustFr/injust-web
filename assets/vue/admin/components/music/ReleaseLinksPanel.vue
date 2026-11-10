<script setup>
import { Copy, Eye, EyeOff, RefreshCw } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { finderEnvKeys, hiddenAfter, isFound, linkRows, missingFinders, reportKind, sourceBadge } from '../../releaseLinks.js';

const props = defineProps({ release: { type: Object, required: true }, finders: { type: Array, required: true } });
const { t, locale } = useI18n();
const api = useApi();
const toast = useToast();
const rows = computed(() => linkRows(props.release));
const missing = computed(() => missingFinders(props.finders));
const checked = computed(() => props.release.linksCheckedAt ? new Date(props.release.linksCheckedAt).toLocaleString(locale.value) : null);
const base = `/api/admin/music/releases/${props.release.id}`;

async function fetchLinks() {
    const answer = await api.post(`${base}/links/refresh`);
    Object.entries(answer.report).forEach(([platform, status]) => {
        toast[reportKind(status)](`${t(`admin.music.platforms.${platform}`)}: ${t(`admin.music.listen.report.${status}`)}`);
    });
}

const refresh = () => toast.attempt(fetchLinks);

const setHidden = (platform, hide) => toast.attempt(async () => {
    await api.put(`${base}/hidden-platforms`, { platforms: hiddenAfter(props.release.hiddenPlatforms, platform, hide) });
    if (!hide) await fetchLinks();
});

const copy = () => toast.attempt(() => navigator.clipboard.writeText(props.release.listenUrl), t('admin.music.listen.copied'));
</script>

<template>
    <section class="listen">
        <h3 class="listen__title">{{ t('admin.music.listen.title') }}</h3>
        <p v-if="missing.length > 0" class="listen__notice" role="note">
            {{ t('admin.music.listen.notConfigured') }}
            <span v-for="finder in missing" :key="finder.platform" class="listen__keys">{{ t(`admin.music.platforms.${finder.platform}`) }}: {{ finderEnvKeys(finder.platform).join(', ') }}.</span>
        </p>
        <div class="listen__url">
            <input type="text" readonly :value="release.listenUrl" :aria-label="t('admin.music.listen.url')" />
            <IconButton :icon="Copy" :label="t('admin.music.listen.copy')" @click="copy" />
        </div>
        <ul class="listen__rows">
            <li v-for="row in rows" :key="row.platform" class="listen__row">
                <span class="listen__platform">{{ t(`admin.music.platforms.${row.platform}`) }}</span>
                <a v-if="row.url" class="listen__link" :href="row.url" target="_blank" rel="noopener noreferrer">{{ row.url }}</a>
                <span v-else class="listen__link listen__link--none">{{ row.hidden ? t('admin.music.listen.hidden') : t('admin.music.listen.none') }}</span>
                <span v-if="row.source" class="listen__badge" :title="sourceBadge(row.source).hint ? t(sourceBadge(row.source).hint) : undefined">{{ t(sourceBadge(row.source).label) }}</span>
                <span class="listen__clicks tabular">{{ t('admin.music.listen.clicks', { count: row.clicks }) }}</span>
                <IconButton v-if="isFound(row.source)" :icon="EyeOff" :label="t('admin.music.listen.hide')" @click="setHidden(row.platform, true)" />
                <IconButton v-else-if="row.hidden" :icon="Eye" :label="t('admin.music.listen.restore')" @click="setHidden(row.platform, false)" />
            </li>
        </ul>
        <div class="listen__actions">
            <BaseButton variant="ghost" :disabled="!release.upc" @click="refresh"><RefreshCw size="1rem" aria-hidden="true" />{{ t('admin.music.listen.fetch') }}</BaseButton>
            <span class="listen__checked">{{ checked ? t('admin.music.listen.checked', { date: checked }) : t('admin.music.listen.neverChecked') }}</span>
        </div>
        <p v-if="!release.upc" class="listen__checked">{{ t('admin.music.listen.needsUpc') }}</p>
    </section>
</template>

<style scoped>
.listen { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.listen__title { margin: 0; color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.listen__notice { margin: 0; padding: var(--space-2) var(--space-3); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-size: var(--font-size-xs); }
.listen__keys { display: block; font-family: var(--font-mono, monospace); }
.listen__url { display: flex; align-items: center; gap: var(--space-2); }
.listen__url input { flex: 1; min-width: 0; }
.listen__rows { display: flex; flex-direction: column; gap: var(--space-1); margin: 0; padding: 0; list-style: none; }
.listen__row { display: flex; align-items: center; gap: var(--space-2); min-height: 2rem; }
.listen__platform { flex: 0 0 8rem; font-weight: 600; }
.listen__link { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.listen__link--none { color: var(--color-muted); }
.listen__badge { padding: 0 var(--space-2); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-size: var(--font-size-xs); }
.listen__clicks { color: var(--color-muted); font-size: var(--font-size-xs); }
.listen__actions { display: flex; align-items: center; gap: var(--space-3); }
.listen__checked { margin: 0; color: var(--color-muted); font-size: var(--font-size-xs); }
</style>
