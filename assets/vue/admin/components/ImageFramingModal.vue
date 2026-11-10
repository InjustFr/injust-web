<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import FocusPicker from '../../components/ui/FocusPicker.vue';
import { useImageFocus } from '../../composables/useImageFraming.js';
import { useToast } from '../../composables/useToast.js';
import { CENTER } from '../../shared/focusPoint.js';
import { SLOTS } from '../../shared/imageFraming.js';
import { imageUrl } from '../settingsForm.js';

const props = defineProps({
    imageId: { type: String, default: null },
    kind: { type: String, required: true },
});
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const toast = useToast();
const focuses = useImageFocus();
const focus = ref({ ...CENTER });
const loaded = ref(false);
const saving = ref(false);
const frames = computed(() => SLOTS[props.kind].frames.map((frame) => ({ label: t(`admin.framing.frames.${frame.key}`), ratio: frame.ratio })));

watch(open, async (isOpen) => {
    if (!isOpen) return;
    loaded.value = false;
    await toast.attempt(async () => {
        focus.value = { ...(await focuses.load(props.imageId)) };
        loaded.value = true;
    });
});

async function save() {
    saving.value = true;
    const done = await toast.attempt(() => focuses.save(props.imageId, focus.value), t('admin.framing.saved'));
    saving.value = false;
    if (done) open.value = false;
}
</script>

<template>
    <BaseModal v-model:open="open" :title="t('admin.framing.title')">
        <form class="framing" @submit.prevent="save">
            <p class="framing__hint">{{ t('admin.framing.hint') }}</p>
            <FocusPicker
                v-if="loaded && imageId"
                v-model="focus"
                :src="imageUrl(imageId)"
                :label="t('admin.framing.point')"
                :value-text="t('admin.framing.pointValue', focus)"
                :frames="frames"
            />
            <div class="framing__actions">
                <BaseButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                <BaseButton type="submit" :loading="saving" :disabled="!loaded">{{ t('admin.framing.save') }}</BaseButton>
            </div>
        </form>
    </BaseModal>
</template>

<style scoped>
.framing { display: flex; flex-direction: column; gap: var(--space-4); }
.framing__hint { margin: 0; color: var(--color-muted); font-size: var(--font-size-sm); }
.framing__actions { display: flex; justify-content: flex-end; gap: var(--space-3); }
</style>
