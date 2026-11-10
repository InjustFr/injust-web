<script setup>
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import PageSection from '../../components/ui/PageSection.vue';
import { useApi } from '../../composables/useApi.js';
import { useImageFraming } from '../../composables/useImageFraming.js';
import { useToast } from '../../composables/useToast.js';
import { imageUpload, imageUrl } from '../settingsForm.js';
import ImageFramingModal from './ImageFramingModal.vue';

const SLOTS = [
    { name: 'logo', field: 'logoImage', framed: false },
    { name: 'hero', field: 'heroImage', framed: true },
];

defineProps({ settings: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const framing = useImageFraming('hero', imageUrl);

function upload({ name, field, framed }, event) {
    const body = imageUpload(event.target.files);
    event.target.value = '';
    if (!body) return;
    toast.attempt(async () => {
        const settings = await api.post(`/api/admin/settings/images/${name}`, body);
        if (framed) framing.uploaded(settings[field]);
    }, t('admin.settings.saved'));
}

function remove(slot) {
    toast.attempt(() => api.del(`/api/admin/settings/images/${slot}`), t('admin.settings.saved'));
}
</script>

<template>
    <PageSection :title="t('admin.settings.images.title')">
        <div class="images">
            <div v-for="slot in SLOTS" :key="slot.name" class="images__slot">
                <span class="images__label">{{ t(`admin.settings.images.${slot.name}`) }}</span>
                <img v-if="settings[slot.field]" class="images__preview" :src="imageUrl(settings[slot.field])" :alt="t(`admin.settings.images.${slot.name}`)" />
                <p v-else class="images__empty">{{ t('admin.settings.images.none') }}</p>
                <div class="images__actions">
                    <label class="images__upload">
                        {{ t('admin.settings.images.upload') }}
                        <input class="visually-hidden" type="file" accept="image/*" :name="`image-${slot.name}`" @change="upload(slot, $event)" />
                    </label>
                    <BaseButton v-if="slot.framed && settings[slot.field]" variant="secondary" @click="framing.adjust(settings[slot.field])">{{ t('admin.framing.adjust') }}</BaseButton>
                    <ConfirmButton v-if="settings[slot.field]" :label="t('admin.settings.images.remove')" @confirm="remove(slot.name)" />
                </div>
            </div>
        </div>
        <ImageFramingModal v-model:open="framing.state.open" :image-id="framing.state.imageId" :kind="framing.state.kind" />
    </PageSection>
</template>

<style scoped>
.images { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: var(--space-4); }
.images__slot { display: flex; flex-direction: column; gap: var(--space-2); }
.images__label { color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.images__preview { max-width: 100%; max-height: 10rem; object-fit: contain; align-self: flex-start; border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-hover); }
.images__empty { color: var(--color-subtle); font-size: var(--font-size-sm); }
.images__actions { display: flex; align-items: center; gap: var(--space-3); }
.images__upload { padding: var(--space-2) var(--space-4); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-weight: 600; cursor: pointer; }
.images__upload:focus-within { outline: 0.125rem solid var(--color-accent); }
</style>
