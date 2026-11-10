<script setup>
import { Upload, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import { useApi } from '../../../composables/useApi.js';
import { useFileUpload } from '../../../composables/useFileUpload.js';
import { useToast } from '../../../composables/useToast.js';
import { intlLocale } from '../../../i18n/locale.js';
import { FILE_ACCEPT, FILE_LIMITS, exceedsLimit, fileUrl, formatSize, slotFile } from '../../beatsForm.js';
import { isBusy } from '../../../shared/uploadState.js';

const props = defineProps({ beat: { type: Object, required: true }, kind: { type: String, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const input = ref(null);
const over = ref(false);
const url = computed(() => fileUrl(props.beat.id, props.kind));
const file = computed(() => slotFile(props.beat, props.kind));
const size = (bytes) => formatSize(bytes, intlLocale());
const { state, start, cancel, reset } = useFileUpload(url.value, { onDone: () => api.refreshAll() });
const busy = computed(() => isBusy(state.value));

const warn = (event) => event.preventDefault();
watch(busy, (isUploading) => window[isUploading ? 'addEventListener' : 'removeEventListener']('beforeunload', warn));
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warn);
    cancel();
});

function pick(files) {
    const chosen = files[0];
    if (!chosen || busy.value) return;
    if (exceedsLimit(props.kind, chosen)) {
        toast.error(t('admin.beats.files.tooLarge', { name: chosen.name, size: size(FILE_LIMITS[props.kind]) }));
        return;
    }
    start(chosen);
}

function choose(event) {
    pick(event.target.files);
    event.target.value = '';
}

function drop(event) {
    over.value = false;
    pick(event.dataTransfer.files);
}

const remove = () => toast.attempt(() => api.del(url.value), t('admin.beats.files.removed'));
</script>

<template>
    <div class="slot" :class="{ 'slot--over': over, 'slot--busy': busy }" @dragover.prevent="over = true" @dragleave="over = false" @drop.prevent="drop">
        <div class="slot__head">
            <span class="slot__name">{{ t(`admin.beats.slots.${kind}`) }}</span>
            <span class="slot__hint">{{ t(kind === 'preview' ? 'admin.beats.files.public' : 'admin.beats.files.private') }} {{ t('admin.beats.files.limit', { size: size(FILE_LIMITS[kind]) }) }}</span>
        </div>

        <div v-if="file" class="slot__file">
            <span class="slot__filename">{{ file.originalName }}</span>
            <span class="slot__size tabular">{{ size(file.size) }}</span>
            <ConfirmButton v-if="!busy" :label="t('admin.beats.remove')" @confirm="remove" />
        </div>
        <p v-else class="slot__empty">{{ t('admin.beats.files.empty') }}</p>
        <audio v-if="kind === 'preview' && file" class="slot__audio" controls preload="none" :src="`/media/audio/${file.id}.mp3`" />

        <div v-if="busy" class="slot__progress">
            <progress :value="state.percent" max="100" :aria-label="t(`admin.beats.slots.${kind}`)" />
            <span class="slot__status tabular" role="status">{{ state.status === 'processing' ? t('admin.beats.files.processing') : t('admin.beats.files.uploading', { percent: state.percent }) }}</span>
            <IconButton :icon="X" :label="t('admin.beats.files.cancel')" @click="cancel" />
        </div>
        <div v-else class="slot__actions">
            <BaseButton variant="secondary" @click="input.click()"><Upload size="1rem" aria-hidden="true" />{{ file ? t('admin.beats.files.replace') : t('admin.beats.files.choose') }}</BaseButton>
            <span class="slot__drop">{{ t('admin.beats.files.drop') }}</span>
            <input ref="input" class="visually-hidden" type="file" :name="`file-${kind}`" :accept="FILE_ACCEPT[kind]" tabindex="-1" @change="choose" />
        </div>
        <p v-if="state.status === 'failed'" class="slot__message slot__message--error" role="alert">
            {{ t('admin.beats.files.failed', { error: state.error }) }}
            <button type="button" class="slot__dismiss" @click="reset">{{ t('admin.beats.files.dismiss') }}</button>
        </p>
        <p v-else-if="state.status === 'cancelled'" class="slot__message" role="status">{{ t('admin.beats.files.cancelled') }}</p>
    </div>
</template>

<style scoped>
.slot { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); border: 0.0625rem dashed var(--color-border-strong); border-radius: var(--radius); }
.slot--over { border-color: var(--color-accent); background: var(--color-accent-soft); }
.slot__head { display: flex; flex-wrap: wrap; align-items: baseline; gap: var(--space-1) var(--space-3); }
.slot__name { font-weight: 600; }
.slot__hint { color: var(--color-subtle); font-size: var(--font-size-sm); }
.slot__file { display: flex; align-items: center; gap: var(--space-3); }
.slot__filename { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.slot__size { color: var(--color-muted); font-size: var(--font-size-sm); }
.slot__empty { margin: 0; color: var(--color-muted); font-size: var(--font-size-md); }
.slot__audio { width: 100%; }
.slot__actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
.slot__drop { color: var(--color-subtle); font-size: var(--font-size-sm); }
.slot__progress { display: flex; align-items: center; gap: var(--space-3); }
.slot__progress progress { flex: 1; min-width: 0; height: 0.5rem; accent-color: var(--color-accent); }
.slot__status { min-width: 7rem; color: var(--color-muted); font-size: var(--font-size-sm); }
.slot__message { margin: 0; color: var(--color-muted); font-size: var(--font-size-sm); }
.slot__message--error { color: var(--color-danger); }
.slot__dismiss { padding: 0; border: 0; background: none; color: inherit; text-decoration: underline; cursor: pointer; }
</style>
