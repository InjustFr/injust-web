<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import FormField from '../../../components/ui/FormField.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { imageUpload, imageUrl } from '../../settingsForm.js';
import { textForm, textPayload } from '../../musicForm.js';

const props = defineProps({ text: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(textForm(props.text));
const errors = ref({});
const saving = ref(false);
const FIELDS = ['tagline', 'manifesto', 'bio'];
const LANGUAGES = ['fr', 'en'];

watch(() => props.text, (text) => Object.assign(form, textForm(text)));

async function save() {
    errors.value = {};
    saving.value = true;
    await toast.attempt(async () => {
        try {
            await api.put('/api/admin/music/text', textPayload(form));
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.music.saved'));
    saving.value = false;
}

function upload(event) {
    const body = imageUpload(event.target.files);
    event.target.value = '';
    if (body) toast.attempt(() => api.post('/api/admin/music/portrait', body), t('admin.music.saved'));
}

const removePortrait = () => toast.attempt(() => api.del('/api/admin/music/portrait'), t('admin.music.saved'));
</script>

<template>
    <PageSection :title="t('admin.music.tabs.texts')">
        <form class="texts" @submit.prevent="save">
            <template v-for="field in FIELDS" :key="field">
                <FormField v-for="language in LANGUAGES" :key="language" :label="t(`admin.music.texts.${field}`, { language: language.toUpperCase() })" :error="errors[`${field}.${language}`] ?? null">
                    <textarea v-model="form[field][language]" :name="`${field}-${language}`" :rows="field === 'tagline' ? 2 : 6" />
                </FormField>
            </template>
            <FormField :label="t('admin.music.texts.hooks')" :hint="t('admin.music.texts.hooksHint')" :error="errors.hooks ?? null">
                <textarea v-model="form.hooks" name="hooks" rows="6" />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.music.save') }}</BaseButton>
        </form>
    </PageSection>
    <PageSection :title="t('admin.music.portrait.title')">
        <img v-if="text.portrait" class="portrait" :src="imageUrl(text.portrait)" :alt="t('admin.music.portrait.title')" />
        <p v-else class="portrait__empty">{{ t('admin.music.portrait.none') }}</p>
        <div class="portrait__actions">
            <label class="portrait__upload">
                {{ t('admin.music.upload') }}
                <input class="visually-hidden" type="file" accept="image/*" name="portrait" @change="upload" />
            </label>
            <ConfirmButton v-if="text.portrait" :label="t('admin.music.remove')" @confirm="removePortrait" />
        </div>
    </PageSection>
</template>

<style scoped>
.texts { display: flex; flex-direction: column; gap: var(--space-3); }
.texts > .button { align-self: flex-start; }
.portrait { max-width: 100%; max-height: 14rem; align-self: flex-start; object-fit: contain; border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.portrait__empty { color: var(--color-subtle); font-size: var(--font-size-sm); }
.portrait__actions { display: flex; align-items: center; gap: var(--space-3); }
.portrait__upload { padding: var(--space-2) var(--space-4); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-weight: 600; cursor: pointer; }
.portrait__upload:focus-within { outline: 0.125rem solid var(--color-accent); }
</style>
