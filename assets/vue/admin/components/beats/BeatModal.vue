<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import BaseSelect from '../../../components/ui/BaseSelect.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import FormField from '../../../components/ui/FormField.vue';
import { useApi } from '../../../composables/useApi.js';
import { useImageFocus, useImageFraming } from '../../../composables/useImageFraming.js';
import { useToast } from '../../../composables/useToast.js';
import { imageUpload, imageUrl } from '../../settingsForm.js';
import ImageFramingModal from '../ImageFramingModal.vue';
import { KEYS, beatForm, beatPayload, keyLabel } from '../../beatsForm.js';
import ToggleRow from '../ToggleRow.vue';

const props = defineProps({ beat: { type: Object, default: null } });
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const framing = useImageFraming('cover', imageUrl);
const focus = useImageFocus();
const form = reactive(beatForm(null));
const errors = ref({});
const saving = ref(false);
const keys = KEYS.map((value) => ({ value, label: keyLabel(value) }));

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, beatForm(props.beat));
});

async function save() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            const body = beatPayload(form);
            await (props.beat ? api.put(`/api/admin/beats/${props.beat.id}`, body) : api.post('/api/admin/beats', body));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.beats.saved'));
    saving.value = false;
    if (done) open.value = false;
}

function upload(event) {
    const body = imageUpload(event.target.files);
    event.target.value = '';
    if (body) toast.attempt(async () => framing.uploaded((await api.post(`/api/admin/beats/${props.beat.id}/cover`, body)).cover), t('admin.beats.saved'));
}

const removeCover = () => toast.attempt(() => api.del(`/api/admin/beats/${props.beat.id}/cover`), t('admin.beats.saved'));
</script>

<template>
    <BaseModal v-model:open="open" :title="beat ? t('admin.beats.beats.edit') : t('admin.beats.beats.new')">
        <form class="beat" @submit.prevent="save">
            <FormField :label="t('admin.beats.beats.titleField')" :error="errors.title ?? null">
                <input v-model="form.title" type="text" name="title" required maxlength="120" />
            </FormField>
            <FormField :label="t('admin.beats.beats.slug')" :hint="t('admin.beats.beats.slugHint')" :error="errors.slug ?? null">
                <input v-model="form.slug" type="text" name="slug" maxlength="120" />
            </FormField>
            <FormField :label="t('admin.beats.beats.bpm')" :error="errors.bpm ?? null">
                <input v-model="form.bpm" type="number" name="bpm" min="40" max="300" required />
            </FormField>
            <FormField :label="t('admin.beats.beats.key')" as="div" :error="errors.key ?? null">
                <BaseSelect v-model="form.key" :options="keys" name="key" />
            </FormField>
            <FormField :label="t('admin.beats.beats.tags')" :hint="t('admin.beats.beats.tagsHint')" :error="errors.tags ?? null">
                <input v-model="form.tags" type="text" name="tags" />
            </FormField>
            <ToggleRow v-model="form.visible" :label="t('admin.beats.beats.visible')" :description="t('admin.beats.beats.visibleHint')" :error="errors.visible ?? null" />

            <div v-if="beat" class="beat__cover">
                <img v-if="beat.cover" class="beat__preview" :src="imageUrl(beat.cover)" :style="{ objectPosition: focus.position(beat.cover) }" :alt="t('admin.beats.beats.cover')" />
                <label class="beat__upload">
                    {{ t('admin.beats.beats.cover') }}
                    <input class="visually-hidden" type="file" accept="image/*" name="cover" @change="upload" />
                </label>
                <BaseButton v-if="beat.cover" variant="secondary" @click="framing.adjust(beat.cover)">{{ t('admin.framing.adjust') }}</BaseButton>
                <ConfirmButton v-if="beat.cover" :label="t('admin.beats.remove')" @confirm="removeCover" />
            </div>

            <BaseButton type="submit" :loading="saving">{{ t('admin.beats.save') }}</BaseButton>
        </form>
        <ImageFramingModal v-model:open="framing.state.open" :image-id="framing.state.imageId" :kind="framing.state.kind" />
    </BaseModal>
</template>

<style scoped>
.beat { display: flex; flex-direction: column; gap: var(--space-3); }
.beat > .button { align-self: flex-start; }
.beat__cover { display: flex; align-items: center; gap: var(--space-3); }
.beat__preview { width: 5rem; height: 5rem; object-fit: cover; border-radius: var(--radius); }
.beat__upload { padding: var(--space-2) var(--space-4); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-weight: 600; cursor: pointer; }
.beat__upload:focus-within { outline: 0.125rem solid var(--color-accent); }
</style>
