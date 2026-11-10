<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import FormField from '../../../components/ui/FormField.vue';
import { useApi } from '../../../composables/useApi.js';
import { useImageFocus, useImageFraming } from '../../../composables/useImageFraming.js';
import { useToast } from '../../../composables/useToast.js';
import { KINDS, portfolioForm, portfolioPayload } from '../../productionForm.js';
import { imageUpload, imageUrl } from '../../settingsForm.js';
import ImageFramingModal from '../ImageFramingModal.vue';

const props = defineProps({ item: { type: Object, default: null } });
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const framing = useImageFraming('cover', imageUrl);
const focus = useImageFocus();
const form = reactive(portfolioForm(null));
const errors = ref({});
const saving = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, portfolioForm(props.item));
});

async function save() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            const body = portfolioPayload(form);
            await (props.item ? api.put(`/api/admin/production/portfolio/${props.item.id}`, body) : api.post('/api/admin/production/portfolio', body));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.production.saved'));
    saving.value = false;
    if (done) open.value = false;
}

function upload(event) {
    const body = imageUpload(event.target.files);
    event.target.value = '';
    if (body) toast.attempt(async () => framing.uploaded((await api.post(`/api/admin/production/portfolio/${props.item.id}/cover`, body)).cover), t('admin.production.saved'));
}

const removeCover = () => toast.attempt(() => api.del(`/api/admin/production/portfolio/${props.item.id}/cover`), t('admin.production.saved'));
</script>

<template>
    <BaseModal v-model:open="open" :title="item ? t('admin.production.portfolio.edit') : t('admin.production.portfolio.new')">
        <form class="item" @submit.prevent="save">
            <FormField :label="t('admin.production.portfolio.titleField')" :error="errors.title ?? null">
                <input v-model="form.title" type="text" name="title" required maxlength="200" />
            </FormField>
            <FormField :label="t('admin.production.portfolio.artist')" :error="errors.artist ?? null">
                <input v-model="form.artist" type="text" name="artist" required maxlength="120" />
            </FormField>
            <fieldset class="item__roles">
                <legend>{{ t('admin.production.portfolio.roles') }}</legend>
                <label v-for="kind in KINDS" :key="kind" class="item__role">
                    <input v-model="form.roles" type="checkbox" name="roles" :value="kind" />
                    {{ t(`admin.production.kinds.${kind}`) }}
                </label>
                <p v-if="errors.roles" class="item__error" role="alert">{{ errors.roles }}</p>
            </fieldset>
            <FormField :label="t('admin.production.portfolio.year')" :error="errors.year ?? null">
                <input v-model="form.year" type="number" name="year" min="1990" max="2100" />
            </FormField>
            <FormField :label="t('admin.production.portfolio.url')" :hint="t('admin.production.portfolio.urlHint')" :error="errors.url ?? null">
                <input v-model="form.url" type="url" name="url" maxlength="255" />
            </FormField>

            <div v-if="item" class="item__cover">
                <img v-if="item.cover" class="item__preview" :src="imageUrl(item.cover)" :style="{ objectPosition: focus.position(item.cover) }" :alt="t('admin.production.portfolio.cover')" />
                <label class="item__upload">
                    {{ t('admin.production.portfolio.cover') }}
                    <input class="visually-hidden" type="file" accept="image/*" name="cover" @change="upload" />
                </label>
                <BaseButton v-if="item.cover" variant="secondary" @click="framing.adjust(item.cover)">{{ t('admin.framing.adjust') }}</BaseButton>
                <ConfirmButton v-if="item.cover" :label="t('admin.production.remove')" @confirm="removeCover" />
            </div>

            <BaseButton type="submit" :loading="saving">{{ t('admin.production.save') }}</BaseButton>
        </form>
        <ImageFramingModal v-model:open="framing.state.open" :image-id="framing.state.imageId" :kind="framing.state.kind" />
    </BaseModal>
</template>

<style scoped>
.item { display: flex; flex-direction: column; gap: var(--space-3); }
.item > .button { align-self: flex-start; }
.item__roles { display: flex; flex-wrap: wrap; gap: var(--space-2) var(--space-4); border: 0; padding: 0; }
.item__roles legend { margin-bottom: var(--space-2); font-weight: 600; }
.item__role { display: inline-flex; align-items: center; gap: var(--space-2); }
.item__error { width: 100%; margin: 0; color: var(--color-danger, var(--color-accent)); }
.item__cover { display: flex; align-items: center; gap: var(--space-3); }
.item__preview { width: 5rem; height: 5rem; object-fit: cover; border-radius: var(--radius); }
.item__upload { padding: var(--space-2) var(--space-4); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-weight: 600; cursor: pointer; }
.item__upload:focus-within { outline: 0.125rem solid var(--color-accent); }
</style>
