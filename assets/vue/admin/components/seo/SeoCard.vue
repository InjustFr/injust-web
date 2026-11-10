<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import FormField from '../../../components/ui/FormField.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { counter, googlePreview, seoForm, seoPayload } from '../../seoForm.js';
import { imageUpload, imageUrl } from '../../settingsForm.js';

const props = defineProps({ page: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(seoForm(props.page));
const errors = ref({});
const saving = ref(false);
const LANGUAGES = ['fr', 'en'];
const FIELDS = ['title', 'description'];
const path = (language) => `${window.location.host}${language === 'fr' ? '/fr' : ''}/${props.page.key}`;

watch(() => props.page, (page) => Object.assign(form, seoForm(page)));

async function save() {
    errors.value = {};
    saving.value = true;
    await toast.attempt(async () => {
        try {
            await api.put(`/api/admin/seo/${props.page.key}`, seoPayload(form));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.seo.saved'));
    saving.value = false;
}

function upload(event) {
    const body = imageUpload(event.target.files);
    event.target.value = '';
    if (!body) return;
    toast.attempt(() => api.post(`/api/admin/seo/${props.page.key}/image`, body), t('admin.seo.saved'));
}

const removeImage = () => toast.attempt(() => api.del(`/api/admin/seo/${props.page.key}/image`), t('admin.seo.saved'));
</script>

<template>
    <PageSection :title="t(`admin.seo.pages.${page.key}`)">
        <form class="seo" @submit.prevent="save">
            <div v-for="language in LANGUAGES" :key="language" class="seo__language">
                <template v-for="field in FIELDS" :key="field">
                    <FormField :label="t(`admin.seo.${field}Field`, { language: language.toUpperCase() })" :error="errors[`${field}.${language}`] ?? null">
                        <textarea v-if="field === 'description'" v-model="form[field][language]" :name="`${field}-${language}`" rows="3" />
                        <input v-else v-model="form[field][language]" type="text" :name="`${field}-${language}`" />
                    </FormField>
                    <p :class="['seo__counter', 'tabular', `seo__counter--${counter(form[field][language], field).state}`]">
                        {{ counter(form[field][language], field).length }} / {{ counter(form[field][language], field).max }}
                        <template v-if="counter(form[field][language], field).state === 'long'"> · {{ t('admin.seo.tooLong') }}</template>
                    </p>
                </template>
                <div class="seo__preview" :aria-label="t('admin.seo.preview')">
                    <span class="seo__preview-url">{{ path(language) }}</span>
                    <span class="seo__preview-title">{{ googlePreview({ title: form.title[language], description: form.description[language] }).title || t('admin.seo.defaultTitle') }}</span>
                    <span class="seo__preview-description">{{ googlePreview({ title: form.title[language], description: form.description[language] }).description || t('admin.seo.defaultDescription') }}</span>
                </div>
            </div>
            <div class="seo__image">
                <span class="seo__image-label">{{ t('admin.seo.image') }}</span>
                <img v-if="page.image" class="seo__image-preview" :src="imageUrl(page.image)" :alt="t('admin.seo.image')" />
                <p v-else class="seo__hint">{{ t('admin.seo.noImage') }}</p>
                <div class="seo__actions">
                    <label class="seo__upload">
                        {{ t('admin.seo.upload') }}
                        <input class="visually-hidden" type="file" accept="image/*" :name="`image-${page.key}`" @change="upload" />
                    </label>
                    <ConfirmButton v-if="page.image" :label="t('admin.seo.remove')" @confirm="removeImage" />
                </div>
            </div>
            <BaseButton type="submit" :loading="saving">{{ t('admin.seo.save') }}</BaseButton>
        </form>
    </PageSection>
</template>

<style scoped>
.seo { display: flex; flex-direction: column; gap: var(--space-3); }
.seo > .button { align-self: flex-start; }
.seo__language { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.seo__counter { margin: 0; color: var(--color-muted); font-size: var(--font-size-xs); }
.seo__counter--long { color: var(--color-ink); font-weight: 600; }
.seo__counter--over { color: var(--color-danger); font-weight: 600; }
.seo__preview { display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-3); background: var(--color-hover); border-radius: var(--radius-inner); }
.seo__preview-url { color: var(--color-muted); font-size: var(--font-size-xs); }
.seo__preview-title { font-size: var(--font-size-lg); font-weight: 600; }
.seo__preview-description { color: var(--color-muted); font-size: var(--font-size-sm); }
.seo__image { display: flex; flex-direction: column; gap: var(--space-2); }
.seo__image-label { color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.seo__image-preview { max-width: 100%; max-height: 10rem; object-fit: contain; align-self: flex-start; border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-hover); }
.seo__hint { color: var(--color-subtle); font-size: var(--font-size-sm); margin: 0; }
.seo__actions { display: flex; align-items: center; gap: var(--space-3); }
.seo__upload { padding: var(--space-2) var(--space-4); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-weight: 600; cursor: pointer; }
.seo__upload:focus-within { outline: 0.125rem solid var(--color-accent); }
</style>
